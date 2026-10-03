<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

const MODELO = 'gemini-3.6-flash';
const MAX_BYTES = 10 * 1024 * 1024; // 10 MB

function responder(int $codigo, array $datos): never
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function cargarEnv(string $ruta): array
{
    if (!is_file($ruta)) {
        return [];
    }
    $env = [];
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $env[trim($clave)] = trim($valor, " \t\"'");
    }
    return $env;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['error' => 'Método no permitido. Usá POST.']);
}

$env = cargarEnv(__DIR__ . '/.env');
$apiKey = $env['GEMINI_API_KEY'] ?? '';
if ($apiKey === '') {
    responder(500, ['error' => 'Falta GEMINI_API_KEY en el archivo .env']);
}

if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    responder(400, ['error' => 'No se recibió la imagen (campo "imagen").']);
}

$archivo = $_FILES['imagen'];

if ($archivo['size'] > MAX_BYTES) {
    responder(400, ['error' => 'La imagen supera el máximo de 10 MB.']);
}

$mime = mime_content_type($archivo['tmp_name']);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    responder(400, ['error' => 'Formato no soportado. Usá JPG, PNG o WebP.']);
}

$prompt = <<<'PROMPT'
Analiza la comida de la imagen y responde estrictamente en formato JSON con la siguiente estructura:
{
  "plato": "nombre del plato",
  "ingredientes": [
    {"nombre": "ingrediente", "porcion_estimada_g": 100, "calorias": 150}
  ],
  "calorias_totales": 0,
  "macronutrientes": {
    "proteinas_g": 0,
    "carbohidratos_g": 0,
    "grasas_g": 0
  }
}
PROMPT;

$body = [
    'contents' => [[
        'parts' => [
            ['text' => $prompt],
            ['inline_data' => [
                'mime_type' => $mime,
                'data' => base64_encode(file_get_contents($archivo['tmp_name'])),
            ]],
        ],
    ]],
    'generationConfig' => [
        'response_mime_type' => 'application/json',
    ],
];

$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
     . MODELO . ':generateContent?key=' . urlencode($apiKey);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_TIMEOUT => 60,
]);

$respuesta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($respuesta === false) {
    responder(502, ['error' => "No se pudo conectar con Gemini: $curlError"]);
}

$json = json_decode($respuesta, true);

if ($httpCode !== 200) {
    $msg = $json['error']['message'] ?? 'Error desconocido de la API';
    responder(502, ['error' => "Gemini respondió $httpCode: $msg"]);
}

$texto = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
if ($texto === null) {
    responder(502, ['error' => 'Gemini no devolvió contenido.', 'raw' => $json]);
}

$resultado = json_decode($texto, true);
if ($resultado === null) {
    responder(502, ['error' => 'La respuesta de Gemini no es JSON válido.', 'raw' => $texto]);
}

responder(200, $resultado);
