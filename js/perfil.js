// perfil.js - VERSIÓN CON MOTOR DE SUBIDA DE IMAGEN
let iti;
const URL_API_PERFIL = window.getApi('perfil_data.php');

function initPhoneInput(currentValue) {
    const input = document.querySelector("#p_telefono");
    if (iti) iti.destroy();

    iti = window.intlTelInput(input, {
        initialCountry: "cl",
        onlyCountries: ["cl"],
        separateDialCode: true,
        utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js",
    });

    let clean = currentValue ? currentValue.replace('+56', '').trim() : '';
    if (clean.length > 9) clean = clean.slice(-9);
    input.value = clean;
}

async function loadProfileData() {
    const emailActual = window.obtenerEmailLimpio();

    if (!emailActual) {
        document.getElementById('display-name').innerText = 'Sesión Inválida';
        document.getElementById('display-surname').innerText = 'Por favor, inicia sesión en la App';
        return;
    }

    try {
        const separador = URL_API_PERFIL.includes('?') ? '&' : '?';
        const endpoint = `${URL_API_PERFIL}${separador}action=get_profile`; // Email lo saca seguro del backend

        const response = await fetch(endpoint);
        const result = await response.json();

        if (result.status === 'success') {
            const d = result.data;
            document.getElementById('display-name').innerText = d.nombre || 'Usuario';
            document.getElementById('display-surname').innerText = d.apellido || '';
            document.getElementById('view-full').innerText = (d.nombre || '') + " " + (d.apellido || '');
            document.getElementById('view-phone').innerText = d.telefono || '--';
            document.getElementById('view-birth').innerText = d.fecha_nacimiento || '--';
            document.getElementById('view-email').innerText = d.email;

            document.getElementById('p_nombre').value = d.nombre || '';
            document.getElementById('p_apellido').value = d.apellido || '';
            document.getElementById('p_fecha_nac').value = d.fecha_nacimiento || '';
            document.getElementById('p_email_display').value = d.email;

            initPhoneInput(d.telefono || '');

            if (d.foto_url) {
                const img = document.getElementById('user-photo');
                img.src = d.foto_url;
                img.style.display = 'block';
                document.getElementById('avatar-placeholder').style.display = 'none';
            }
        } else {
            document.getElementById('display-name').innerText = 'Bienvenido';
            document.getElementById('display-surname').innerText = 'Completa tu perfil';
            document.getElementById('view-email').innerText = emailActual;
            document.getElementById('p_email_display').value = emailActual;
            initPhoneInput('');
        }
    } catch (e) {
        console.error("Error conectando:", e);
        document.getElementById('display-name').innerText = 'Error de conexión';
    }
}

// --- PREVISUALIZACIÓN AL SELECCIONAR FOTO ---
const inputFile = document.getElementById('p_foto_file');
const imgDisplay = document.getElementById('user-photo');
const imgPlaceholder = document.getElementById('avatar-placeholder');

if (inputFile) {
    inputFile.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({ icon: 'error', title: 'Archivo muy pesado', text: 'Máximo 5MB.', ...window.swalConfig });
                this.value = ''; return;
            }
            const reader = new FileReader();
            reader.onload = function (e) {
                imgDisplay.src = e.target.result;
                imgDisplay.style.display = 'block';
                imgPlaceholder.style.display = 'none';
                document.querySelector('.profile-avatar').style.borderColor = '#E98C00'; // Indicador visual de cambio
            }
            reader.readAsDataURL(file);
        }
    });
}

// --- GUARDAR DATOS ---
document.getElementById('profileForm').onsubmit = async (e) => {
    e.preventDefault();

    const phoneInput = document.querySelector("#p_telefono");
    const errorMsg = document.getElementById('phone-error');
    const digitsOnly = phoneInput.value.replace(/\D/g, '');

    if (digitsOnly.length !== 9) {
        errorMsg.style.display = 'block';
        phoneInput.style.border = '2px solid #cc0000 !important';
        return;
    } else {
        errorMsg.style.display = 'none';
        phoneInput.style.border = '1px solid #ddd !important';
    }

    const btnSubmit = document.querySelector('.m-btn-save');
    btnSubmit.innerText = 'GUARDANDO...';
    btnSubmit.disabled = true;

    const fullPhone = "+56" + digitsOnly;
    const formData = new FormData();
    formData.append('action', 'update_profile');
    formData.append('nombre', document.getElementById('p_nombre').value);
    formData.append('apellido', document.getElementById('p_apellido').value);
    formData.append('telefono', fullPhone);
    formData.append('fecha_nacimiento', document.getElementById('p_fecha_nac').value);

    // Si el usuario seleccionó una imagen nueva, la inyectamos al formData
    if (inputFile && inputFile.files[0]) {
        formData.append('foto_file', inputFile.files[0]);
    }

    try {
        const response = await fetch(URL_API_PERFIL, {
            method: 'POST',
            body: formData
        });

        const res = await response.json();

        if (res.status === 'success') {
            const overlay = document.getElementById('m-overlay');
            document.getElementById('m-modal-text').innerText = "¡Perfil Actualizado!";
            overlay.classList.add('show');
            setTimeout(() => { location.reload(); }, 1500);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message, ...window.swalConfig });
        }
    } catch (e) {
        console.error("Error guardando datos:", e);
        Swal.fire({ icon: 'error', title: 'Fallo de conexión', text: 'Error al enviar datos.', ...window.swalConfig });
    } finally {
        btnSubmit.innerText = 'GUARDAR';
        btnSubmit.disabled = false;
    }
};

function enableEditMode() {
    document.getElementById('view-mode').style.display = 'none';
    document.getElementById('profileForm').style.display = 'block';
    setTimeout(() => { if (iti) iti.setCountry("cl"); }, 50);
}

function disableEditMode() {
    document.getElementById('view-mode').style.display = 'block';
    document.getElementById('profileForm').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', loadProfileData);