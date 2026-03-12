
<?php
/* Template Name: Login App */
get_header(); 
?>

<?php
/*
Template Name: Login App
*/
if ( is_user_logged_in() ) {
    wp_redirect( home_url('/') );
    exit;
}

// Necesitarás tu Client ID de Google (El que termina en apps.googleusercontent.com)
$google_client_id = "377569852818-k7p5cmhk6emmqnveb1ro3uo5vjpl63me.apps.googleusercontent.com"; 
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso | Tabolango</title>
    <?php wp_head(); ?>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body style="background: linear-gradient(135deg, #165c38 0%, #0F4B29 50%, #09381f 100%);">

    <div class="login-wrapper">
        <div class="login-box">
            <div class="brand-badge">TABOLANGO</div>
            <h2>Acceso Interno</h2>
            <p>Identifícate con tu cuenta autorizada para continuar.</p>

            <div class="google-btn-container" style="display:flex; justify-content:center; margin-top:20px;">
                <div id="g_id_onload"
                     data-client_id="<?php echo $google_client_id; ?>"
                     data-context="signin"
                     data-ux_mode="popup"
                     data-callback="procesarLoginNativoGoogle"
                     data-auto_prompt="false">
                </div>

                <div class="g_id_signin"
                     data-type="standard"
                     data-shape="rectangular"
                     data-theme="outline"
                     data-text="signin_with"
                     data-size="large"
                     data-logo_alignment="left">
                </div>
            </div>
        </div>
    </div>

    <script>
    function procesarLoginNativoGoogle(response) {
        Swal.fire({
            title: 'Autenticando...',
            text: 'Verificando credenciales',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const formData = new FormData();
        formData.append('action', 'login_nativo_tabolango');
        formData.append('token', response.credential);

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                window.location.href = '<?php echo home_url("/"); ?>'; 
            } 
            // 🔥 LA NUEVA MAGIA: Si no está registrado y es externo
            else if (data.status === 'not_registered') {
                Swal.fire({
                    title: 'Acceso Restringido',
                    html: data.message + '<br><br>¿Deseas enviar una solicitud de acceso al administrador?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#0F4B29',
                    cancelButtonColor: '#e74c3c',
                    confirmButtonText: 'Sí, solicitar acceso',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        ejecutarSolicitudAcceso(data.userData);
                    }
                });
            } else {
                // Mensajes de error normales (Ej: Cuenta inactiva)
                Swal.fire('Atención', data.message, 'info');
            }
        })
        .catch(err => {
            Swal.fire('Error', 'Problema de conexión con el servidor', 'error');
        });
    }

    // Función que envía los datos a la BD como inactivos
    function ejecutarSolicitudAcceso(userData) {
        Swal.fire({
            title: 'Enviando solicitud...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const reqData = new FormData();
        reqData.append('action', 'solicitar_acceso_tabolango');
        reqData.append('email', userData.email);
        reqData.append('nombre', userData.nombre);
        reqData.append('apellido', userData.apellido);
        reqData.append('picture', userData.picture);

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
            method: 'POST',
            body: reqData
        })
        .then(res => res.json())
        .then(resData => {
            if (resData.status === 'success') {
                Swal.fire('Solicitud Exitosa', resData.message, 'success');
            } else {
                Swal.fire('Error', resData.message, 'error');
            }
        });
    }
    </script>
</body>
</html>

<?php
get_footer();
?>
