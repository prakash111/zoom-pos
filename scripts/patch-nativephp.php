<?php

$freshCommand = __DIR__ . '/../vendor/nativephp/desktop/src/Commands/FreshCommand.php';
if (file_exists($freshCommand)) {
    $content = file_get_contents($freshCommand);
    if (!str_contains($content, '__construct')) {
        $search = "    protected \$description = 'Drop all tables and re-run all migrations in the NativePHP development environment';";
        $replace = $search . "\n\n    public function __construct(\\Illuminate\\Database\\Migrations\\Migrator \$migrator, \\Illuminate\\Contracts\\Events\\Dispatcher \$dispatcher)\n    {\n        \$this->signature = 'native:'.\$this->signature;\n\n        parent::__construct(\$migrator, \$dispatcher);\n    }";
        $content = str_replace($search, $replace, $content);
        file_put_contents($freshCommand, $content);
        echo "Patched NativePHP FreshCommand constructor.\n";
    }
}
