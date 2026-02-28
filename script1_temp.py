import re

file_path = "c:\\laragon\\www\\Konveksio\\app\\Filament\\Resources\\Orders\\OrderResource.php"

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Hapus ->visible() dari Upload Desain (agar berlaku untuk semua produk)
# Pattern mencari `FileUpload::make('design_image')` sampai penutupnya
content = re.sub(
    r"(FileUpload::make\('design_image'\).*?->columnSpanFull\(\)\n\s*->nullable\(\))\n\s*->visible\(fn\(Get \$get\) => in_array\(\$get\('production_category'\), \['produksi', 'custom'\]\)\)\n",
    r"\1\n",
    content,
    flags=re.DOTALL
)

# 2. Hapus ->visible() dari design_status
content = re.sub(
    r"(Select::make\('design_status'\).*?->default\('pending'\))\n\s*->visible\(fn\(Get \$get\) => in_array\(\$get\('production_category'\), \['produksi', 'custom'\]\)\)\n",
    r"\1\n",
    content,
    flags=re.DOTALL
)

# 3. Pada Section "Sablon / Bordir" (Jasa), tambahkan field design_image/status secara manual jika itu tidak di dalam main array. 
# Tapi sebenarnya karena ini order_items, design_image & status itu field global model,
# Tadi saya cek ternyata TIDAK ADA "FileUpload::make('design_image')" di OrderResource.php??
# Mari saya periksa ulang!
