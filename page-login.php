
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
    // Esta función la llama Google automáticamente cuando el usuario elige su cuenta
    function procesarLoginNativoGoogle(response) {
        // response.credential contiene un Token seguro (JWT)
        
        Swal.fire({
            title: 'Autenticando...',
            text: 'Verificando credenciales',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        // Enviamos el token a nuestro propio backend de WordPress
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
                window.location.href = '<?php echo home_url("/"); ?>'; // Redirigir al inicio
            } else {
                Swal.fire('Acceso Denegado', data.message, 'error');
            }
        })
        .catch(err => {
            Swal.fire('Error', 'Problema de conexión con el servidor', 'error');
        });
    }
    </script>
</body>
</html>

<?php
get_footer();
?>
