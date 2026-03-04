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

<?php wp_footer(); ?>
</body>
</html>