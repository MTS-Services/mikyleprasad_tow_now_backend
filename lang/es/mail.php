<?php

return [

    'password_reset_otp' => [
        'subject' => 'Tu código para restablecer la contraseña',
        'title' => 'Restablecimiento de contraseña',
        'intro' => 'Usa el siguiente código para restablecer tu contraseña. Si no lo solicitaste, puedes ignorar este correo.',
        'expires' => 'Este código caduca en :minutes minutos.',
    ],

    'account_restore_otp' => [
        'subject' => 'Código para cancelar la eliminación de la cuenta',
        'title' => 'Cancelar eliminación de cuenta',
        'intro' => 'Usa el siguiente código para cancelar la eliminación programada de tu cuenta.',
        'expires' => 'Este código caduca en :minutes minutos.',
    ],

    'welcome' => [
        'subject' => 'Bienvenido a SourceNest',
        'preheader' => 'Tu cuenta de SourceNest ya esta lista. Comienza a explorar proveedores hoy.',
    ],

];
