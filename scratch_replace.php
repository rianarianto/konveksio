<?php
$file = 'app/Filament/Resources/ControlProduksis/Pages/AturTugasProduksi.php';
$content = file_get_contents($file);
$content = str_replace(
    [
        "TextInput::make('wage_per_pcs')",
        "TextInput::make('wage_custom_per_pcs')"
    ],
    [
        "TextInput::make('wage_per_pcs')->live(debounce: 500)",
        "TextInput::make('wage_custom_per_pcs')->live(debounce: 500)"
    ],
    $content
);
file_put_contents($file, $content);
echo "Successfully updated wages to live!\n";
