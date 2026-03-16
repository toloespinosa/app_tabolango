<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Lógica del Menú Hamburguesa ---
    const hamTrigger = document.getElementById('hamTrigger');
    const hamDropdown = document.getElementById('hamDropdown');

    if (hamTrigger && hamDropdown) {
        hamTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            hamDropdown.classList.toggle('is-open');
            // Cierra el otro menú si está abierto
            if(userDropdown) userDropdown.classList.remove('show');
        });
    }

    // --- Lógica del Menú de Usuario ---
    const userTrigger = document.getElementById('userTrigger');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userTrigger) {
        const isLogged = userTrigger.getAttribute('data-logged') === 'true';
        const loginUrl = userTrigger.getAttribute('data-login-url');

        userTrigger.addEventListener('click', (e) => {
            if (!isLogged) {
                window.location.href = loginUrl;
            } else if (userDropdown) {
                e.preventDefault();
                e.stopPropagation();
                userDropdown.classList.toggle('show');
                // Cierra el menú hamburguesa si está abierto
                if(hamDropdown) hamDropdown.classList.remove('is-open');
            }
        });
    }

    // --- Cerrar al hacer click fuera ---
    document.addEventListener('click', function(e) {
        if (hamDropdown && !hamDropdown.contains(e.target) && !hamTrigger.contains(e.target)) {
            hamDropdown.classList.remove('is-open');
        }
        if (userDropdown && !userDropdown.contains(e.target) && !userTrigger.contains(e.target)) {
            userDropdown.classList.remove('show');
        }
    });
});
</script>
<?php 
// Solo mostramos la barra si el usuario está logueado
if ( is_user_logged_in() ) : 
    // Reutilizamos el rol que ya calculaste en el header
    $rol_id = function_exists('tabolango_get_user_role') ? tabolango_get_user_role() : 0;
?>
<nav class="mobile-nav">
    <div class="nav-item-wrapper">
        <a href="<?php echo home_url('/pedidos/'); ?>" class="nav-link">
            <span class="nav-icon">📋</span>
            <span class="nav-label">Pedidos</span>
        </a>
    </div>

    <div class="nav-item-wrapper">
        <a href="<?php echo home_url('/entregados/'); ?>" class="nav-link">
            <span class="nav-icon">✅</span>
            <span class="nav-label">Entregados</span>
        </a>
    </div>

    <?php if (in_array($rol_id, [1, 2, 4])) : ?>
    <div class="nav-item-central-wrapper">
        <a href="<?php echo home_url('/ingresar/'); ?>" class="nav-item-central">
            <span class="nav-icon" style="color: #ffffff !important;">+</span>
        </a>
        <span class="nav-label">Nuevo</span>
    </div>

    <div class="nav-item-wrapper">
        <a href="<?php echo home_url('/clientes/'); ?>" class="nav-link">
            <span class="nav-icon">🤝</span>
            <span class="nav-label">Clientes</span>
        </a>
    </div>
    <?php endif; ?>

    <?php if (in_array($rol_id, [1, 2, 3])) : ?>
    <div class="nav-item-wrapper">
        <a href="<?php echo home_url('/tus-autos/'); ?>" class="nav-link">
            <span class="nav-icon">🚛</span>
            <span class="nav-label">Mis Autos</span>
        </a>
    </div>
    <?php endif; ?>
</nav>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>