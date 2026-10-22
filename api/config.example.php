<?php
/*
  Copie este ficheiro para config.php (na mesma pasta) e preencha os valores.
  O config.php NUNCA deve ir para o GitHub (já está no .gitignore) e o .htaccess impede o acesso pelo browser.
*/
return [
    // Dados da base de dados criada na OVHcloud (Área de Cliente → Alojamento → Bases de dados)
    'db_host' => 'COLOCAR_O_SERVIDOR_DA_BASE_DE_DADOS',
    'db_name' => 'COLOCAR_O_NOME_DA_BASE_DE_DADOS',
    'db_user' => 'COLOCAR_O_UTILIZADOR',
    'db_pass' => 'COLOCAR_A_PALAVRA_PASSE',

    // Texto aleatório e longo (30+ caracteres). Serve para guardar o IP de forma anónima (hash).
    'sal' => 'TROCAR_POR_UM_TEXTO_ALEATORIO_LONGO',

    // Palavra-passe do painel /admin/ (guardada em hash).
    // Deixe vazio, abra /admin/gerar-hash.php, copie o resultado para aqui e APAGUE esse ficheiro.
    'admin_hash' => '',
];
