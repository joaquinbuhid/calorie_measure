const dropzone = document.getElementById('dropzone');
const input = document.getElementById('inputImagen');
const preview = document.getElementById('preview');
const dropzoneTexto = document.getElementById('dropzoneTexto');
const btnAnalizar = document.getElementById('btnAnalizar');
const estado = document.getElementById('estado');
const seccionResultado = document.getElementById('resultado');

let archivoSeleccionado = null;

dropzone.addEventListener('click', () => input.click());

input.addEventListener('change', () => {
  if (input.files.length > 0) seleccionarArchivo(input.files[0]);
});

['dragover', 'dragleave', 'drop'].forEach(evento => {
  dropzone.addEventListener(evento, e => {
    e.preventDefault();
    dropzone.classList.toggle('dragover', evento === 'dragover');
  });
});

dropzone.addEventListener('drop', e => {
  const archivo = e.dataTransfer.files[0];
  if (archivo) seleccionarArchivo(archivo);
});

function seleccionarArchivo(archivo) {
  if (!archivo.type.startsWith('image/')) {
    mostrarEstado('El archivo tiene que ser una imagen', true);
    return;
  }
  archivoSeleccionado = archivo;
  preview.src = URL.createObjectURL(archivo);
  preview.hidden = false;
  dropzoneTexto.hidden = true;
  btnAnalizar.disabled = false;
  seccionResultado.hidden = true;
  mostrarEstado('');
}

btnAnalizar.addEventListener('click', async () => {
  if (!archivoSeleccionado) return;

  btnAnalizar.disabled = true;
  seccionResultado.hidden = true;
  mostrarEstado('Analizando imagen...');

  const formData = new FormData();
  formData.append('imagen', archivoSeleccionado);

  try {
    const res = await fetch('api.php', { method: 'POST', body: formData });
    const datos = await res.json();

    if (!res.ok) throw new Error(datos.error || 'Error desconocido');

    mostrarResultado(datos);
    mostrarEstado('');
  } catch (err) {
    mostrarEstado(err.message, true);
  } finally {
    btnAnalizar.disabled = false;
  }
});

function mostrarResultado(datos) {
  document.getElementById('plato').textContent = datos.plato || 'Plato desconocido';

  const tbody = document.getElementById('ingredientes');
  tbody.innerHTML = '';
  for (const ing of datos.ingredientes || []) {
    const fila = document.createElement('tr');
    fila.innerHTML = `<td>${escapar(ing.nombre)}</td>
                      <td>${ing.porcion_estimada_g ?? '-'} g</td>
                      <td>${ing.calorias ?? '-'} kcal</td>`;
    tbody.appendChild(fila);
  }

  document.getElementById('caloriasTotales').textContent = datos.calorias_totales ?? 0;

  const macros = datos.macronutrientes || {};
  const p = macros.proteinas_g || 0;
  const c = macros.carbohidratos_g || 0;
  const g = macros.grasas_g || 0;
  const total = p + c + g || 1;

  document.getElementById('proteinas').textContent = `${p} g`;
  document.getElementById('carbohidratos').textContent = `${c} g`;
  document.getElementById('grasas').textContent = `${g} g`;

  document.getElementById('barraProteinas').style.width = `${(p / total) * 100}%`;
  document.getElementById('barraCarbohidratos').style.width = `${(c / total) * 100}%`;
  document.getElementById('barraGrasas').style.width = `${(g / total) * 100}%`;

  seccionResultado.hidden = false;
}

function mostrarEstado(mensaje, esError = false) {
  estado.textContent = mensaje;
  estado.classList.toggle('error', esError);
}

function escapar(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}
