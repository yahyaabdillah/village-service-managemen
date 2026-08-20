param(
    [string]$MainDirectory = '.codex-work\main-unpacked',
    [string]$FragmentDirectory = '.codex-work\chapter4-unpacked'
)

$ErrorActionPreference = 'Stop'

$root = (Resolve-Path '.').Path
$mainDir = (Resolve-Path (Join-Path $root $MainDirectory)).Path
$fragmentDir = (Resolve-Path (Join-Path $root $FragmentDirectory)).Path
$mainXmlPath = Join-Path $mainDir 'word\document.xml'
$fragmentXmlPath = Join-Path $fragmentDir 'word\document.xml'
$mainRelsPath = Join-Path $mainDir 'word\_rels\document.xml.rels'
$fragmentRelsPath = Join-Path $fragmentDir 'word\_rels\document.xml.rels'
$mainMediaDir = Join-Path $mainDir 'word\media'
$fragmentMediaDir = Join-Path $fragmentDir 'word\media'

[xml]$main = Get-Content -Raw -LiteralPath $mainXmlPath
[xml]$fragment = Get-Content -Raw -LiteralPath $fragmentXmlPath
[xml]$mainRels = Get-Content -Raw -LiteralPath $mainRelsPath
[xml]$fragmentRels = Get-Content -Raw -LiteralPath $fragmentRelsPath

$wUri = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
$rUri = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
$relUri = 'http://schemas.openxmlformats.org/package/2006/relationships'
$aUri = 'http://schemas.openxmlformats.org/drawingml/2006/main'
$wpUri = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing'
$picUri = 'http://schemas.openxmlformats.org/drawingml/2006/picture'

function New-NamespaceManager([xml]$document) {
    $manager = New-Object System.Xml.XmlNamespaceManager($document.NameTable)
    $manager.AddNamespace('w', $wUri)
    $manager.AddNamespace('r', $rUri)
    $manager.AddNamespace('a', $aUri)
    $manager.AddNamespace('wp', $wpUri)
    $manager.AddNamespace('pic', $picUri)
    Write-Output -NoEnumerate $manager
}

$mainNs = New-NamespaceManager $main
$fragmentNs = New-NamespaceManager $fragment
$mainBody = $main.SelectSingleNode('//w:body', $mainNs)
$fragmentBody = $fragment.SelectSingleNode('//w:body', $fragmentNs)

function Get-Text([System.Xml.XmlNode]$node, [System.Xml.XmlNamespaceManager]$ns) {
    return (($node.SelectNodes('.//w:t', $ns) | ForEach-Object { $_.InnerText }) -join '')
}

function Set-ParagraphText([System.Xml.XmlNode]$paragraph, [string]$text) {
    $children = @($paragraph.ChildNodes)
    foreach ($child in $children) {
        if ($child.LocalName -ne 'pPr') { [void]$paragraph.RemoveChild($child) }
    }
    $run = $main.CreateElement('w', 'r', $wUri)
    $textNode = $main.CreateElement('w', 't', $wUri)
    $textNode.InnerText = $text
    [void]$run.AppendChild($textNode)
    [void]$paragraph.AppendChild($run)
}

function Set-CellText([System.Xml.XmlNode]$cell, [string]$text) {
    $properties = $cell.SelectSingleNode('./w:tcPr', $mainNs)
    foreach ($child in @($cell.ChildNodes)) {
        if ($child -ne $properties) { [void]$cell.RemoveChild($child) }
    }
    $paragraph = $main.CreateElement('w', 'p', $wUri)
    $run = $main.CreateElement('w', 'r', $wUri)
    $textNode = $main.CreateElement('w', 't', $wUri)
    $textNode.InnerText = $text
    [void]$run.AppendChild($textNode)
    [void]$paragraph.AppendChild($run)
    [void]$cell.AppendChild($paragraph)
}

$replacements = @{
    'dari hasil wawancara terhadap bapak M. Nur Juniadi' = 'Berdasarkan hasil wawancara dengan Bapak Hendrawan Sritomo, S.STP., M.Si., pelayanan administrasi di Kantor Desa Ngringo masih bertumpu pada penyusunan dokumen secara manual menggunakan template yang telah tersedia. Permohonan yang masuk belum dicatat pada daftar terpusat dan dokumen yang diterbitkan langsung diserahkan kepada pemohon tanpa salinan arsip yang mudah ditelusuri.'
    'Tantangan utama dalam pelaksanaannya adalah besarnya upaya' = 'Kondisi tersebut membuat petugas kesulitan mengetahui permohonan yang belum selesai dan menelusuri dokumen yang pernah diterbitkan. Permohonan dapat terlewat karena tidak terdapat catatan antrean kerja, sedangkan pembuatan ulang dokumen membutuhkan pencarian data dan penyusunan kembali dari awal.'
    'Dari hasil wawancara terhadap beberapa perwakilan alumni' = 'Wawancara juga menunjukkan bahwa masyarakat belum memperoleh pemberitahuan otomatis ketika dokumen selesai. Selama proses yang berlangsung sekitar 2 sampai 7 hari, pemohon harus menghubungi petugas atau datang kembali ke kantor desa untuk mengetahui perkembangan permohonannya.'
    'Berikut merupakan hasil observasi permasalahan pada proses pelaksaanaan' = 'Berikut merupakan hasil identifikasi masalah pada proses pelayanan administrasi di Kantor Desa Ngringo:'
    'Sistem dapat diakses oleh masyarakat umum, tetapi hanya pada tampilan awal dan halaman pendaftaran/login.' = 'KF-01 - Sistem dapat diakses masyarakat melalui beranda, daftar layanan, detail layanan, dan halaman cek status tanpa autentikasi.'
    'Sistem dilengkapi fitur login untuk masyarakat (pemohon) dan petugas Kantor Desa Ngringo (admin).' = 'KF-02 - Sistem menyediakan autentikasi bagi petugas desa menggunakan akun aktif sebelum mengakses halaman administrasi.'
    'Masyarakat dapat mendaftar akun secara mandiri menggunakan data diri (NIK, nama, dan data lain yang diperlukan) sebelum dapat mengajukan permohonan layanan.' = 'KF-03 - Masyarakat dapat mengajukan permohonan tanpa membuat akun dengan mengisi NIK, nama, nomor telepon, dan alamat pemohon.'
    'Sistem memiliki fitur pengajuan layanan yang mencakup jenis-jenis layanan yang diselenggarakan Kantor Desa Ngringo' = 'KF-04 - Sistem menampilkan jenis layanan aktif yang meliputi surat domisili, surat usaha, surat tidak mampu, pengantar KTP atau KK, dan pengaduan masyarakat.'
    'Masyarakat dapat mengunggah berkas persyaratan yang dibutuhkan sesuai dengan jenis layanan yang diajukan.' = 'KF-05 - Masyarakat dapat mengunggah berkas persyaratan sesuai jenis, ukuran, dan ketentuan pada layanan yang dipilih.'
    'Petugas dapat melihat, memverifikasi, memproses, dan menolak permohonan yang masuk melalui halaman dashboard.' = 'KF-06 - Petugas dapat melihat, memverifikasi, memproses, menolak, dan menerbitkan hasil permohonan melalui halaman administrasi.'
    'Sistem memiliki fitur pemantauan status permohonan yang memungkinkan masyarakat melihat sejauh mana permohonannya telah diproses' = 'KF-07 - Masyarakat dapat memantau status permohonan menggunakan kode pengajuan dan NIK tanpa datang ke kantor desa.'
    'Sistem memiliki fitur pemberitahuan otomatis kepada masyarakat pada saat status permohonannya berubah' = 'KF-08 - Sistem mencatat dan menjadwalkan notifikasi WhatsApp secara otomatis ketika status permohonan berubah.'
    'Sistem memiliki fitur pengarsipan dokumen yang menyimpan salinan setiap dokumen yang telah diterbitkan' = 'KF-09 - Sistem menyimpan dokumen final pada arsip privat dan mengizinkan masyarakat mengunduhnya setelah verifikasi kode pengajuan serta NIK.'
    'Sistem memiliki fitur pengaduan masyarakat yang memungkinkan masyarakat menyampaikan keluhan terkait pelayanan' = 'KF-10 - Masyarakat dapat mengirim pengaduan dan petugas dapat menindaklanjuti pengaduan melalui alur status pelayanan.'
    'Petugas diharuskan login terlebih dahulu dan diarahkan ke halaman dashboard yang berisi rekapitulasi jumlah permohonan' = 'KF-11 - Dashboard admin menampilkan rekapitulasi penduduk dan permohonan berdasarkan status serta periode.'
    'Terdapat filter pada dashboard untuk melihat data permohonan berdasarkan jenis layanan, rentang waktu, dan status permohonan.' = 'KF-12 - Petugas dapat memfilter permohonan berdasarkan kata kunci, jenis layanan, status, dan rentang waktu.'
    'Sistem dapat menampilkan riwayat seluruh permohonan yang pernah diajukan oleh masing-masing pemohon.' = 'KF-13 - Sistem menampilkan riwayat perubahan status setiap permohonan secara kronologis sesuai hak akses pengguna.'
    'Petugas dapat mengelola data master jenis layanan' = 'KF-14 - Admin dapat menambah, melihat, mengubah, menonaktifkan, dan menghapus jenis layanan sesuai kebutuhan operasional desa.'
}

foreach ($paragraph in $main.SelectNodes('//w:body/w:p', $mainNs)) {
    $text = Get-Text $paragraph $mainNs
    if ($text -like 'Petugas dapat mengelola data master jenis layanan*') {
        Set-ParagraphText $paragraph 'KF-14 - Admin dapat menambah, melihat, mengubah, menonaktifkan, dan menghapus jenis layanan sesuai kebutuhan operasional desa.'
        $text = Get-Text $paragraph $mainNs
    }
    foreach ($prefix in $replacements.Keys) {
        if ($text.StartsWith($prefix)) {
            Set-ParagraphText $paragraph $replacements[$prefix]
            break
        }
    }
    if ($text -like '*tersedia.Sistem Tracer Study*') {
        Set-ParagraphText $paragraph ($text -replace 'Sistem Tracer Study dilngkapi fitur login untuk alumni dan Pihak Tracer Study \(admin\)\.', '')
    }
    if ($text -eq 'Gambar 4.1. usecase diagram tracer study') {
        Set-ParagraphText $paragraph 'Gambar 4.1. Use case diagram sistem pelayanan Desa Ngringo'
    }
}

$interviewTable = $main.SelectNodes('//w:body/w:tbl', $mainNs) | Where-Object { (Get-Text $_ $mainNs) -like '*Apa saja jenis layanan yang paling banyak diajukan masyarakat*' } | Select-Object -First 1
if ($null -ne $interviewTable) {
    $answers = @(
        'Layanan yang paling banyak diajukan adalah surat pengantar administrasi kependudukan, disusul surat keterangan domisili, surat keterangan usaha, surat keterangan tidak mampu, dan layanan pengaduan.',
        'Masyarakat datang ke kantor, mengambil dan mengisi formulir, menyerahkan persyaratan, kemudian petugas menyusun dokumen dengan menyesuaikan template yang tersedia.',
        'Permohonan belum dicatat dalam daftar khusus. Dokumen dibuat satu kali dan langsung diserahkan kepada pemohon tanpa salinan arsip terpusat.',
        'Waktu penyelesaian rata-rata sekitar 2 sampai 7 hari, tergantung jenis layanan dan kelengkapan persyaratan.',
        'Belum ada fasilitas pemantauan status. Masyarakat harus menghubungi petugas atau datang kembali ke kantor desa.',
        'Pernah terjadi permohonan terlewat atau dokumen sulit ditelusuri karena tidak ada daftar pekerjaan dan arsip yang terhubung dengan permohonan.',
        'Kendala utama adalah pencatatan manual, keterbatasan petugas, banyaknya permohonan, tidak adanya arsip terpusat, dan tidak adanya pemberitahuan penyelesaian.',
        'Belum tersedia sistem pelayanan elektronik yang menangani pengajuan, pemantauan status, pemberitahuan, dan pengarsipan dokumen dalam satu alur.'
    )
    $rows = @($interviewTable.SelectNodes('./w:tr', $mainNs))
    for ($i = 1; $i -lt $rows.Count -and $i -le $answers.Count; $i++) {
        $cells = @($rows[$i].SelectNodes('./w:tc', $mainNs))
        if ($cells.Count -ge 3) { Set-CellText $cells[2] $answers[$i - 1] }
    }
}

$relationshipMap = @{}
$imageRelationships = $fragmentRels.DocumentElement.ChildNodes | Where-Object { $_.Type -eq 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image' }
$relationshipIndex = 1
foreach ($relationship in $imageRelationships) {
    $sourceName = [System.IO.Path]::GetFileName($relationship.Target)
    $targetName = 'chapter4-' + $sourceName
    Copy-Item -LiteralPath (Join-Path $fragmentMediaDir $sourceName) -Destination (Join-Path $mainMediaDir $targetName) -Force

    $newId = 'rIdChapter4_' + $relationshipIndex
    $newRelationship = $mainRels.CreateElement('Relationship', $relUri)
    $newRelationship.SetAttribute('Id', $newId)
    $newRelationship.SetAttribute('Type', $relationship.Type)
    $newRelationship.SetAttribute('Target', 'media/' + $targetName)
    [void]$mainRels.DocumentElement.AppendChild($newRelationship)
    $relationshipMap[$relationship.Id] = $newId
    $relationshipIndex++
}

$startNode = $null
$endNode = $null
foreach ($child in $mainBody.ChildNodes) {
    if ($child.LocalName -ne 'p') { continue }
    $text = Get-Text $child $mainNs
    if ($null -eq $startNode -and $text.Trim() -eq 'User Skenario') { $startNode = $child }
    if ($null -eq $endNode -and $text.Replace(' ', '') -eq 'BABVPENUTUP') { $endNode = $child }
}
if ($null -eq $startNode -or $null -eq $endNode) { throw 'Batas penggantian Bab 4 tidak ditemukan.' }

$cursor = $startNode
while ($cursor -ne $endNode) {
    $next = $cursor.NextSibling
    [void]$mainBody.RemoveChild($cursor)
    $cursor = $next
}

$drawingIndex = 2000
foreach ($fragmentChild in @($fragmentBody.ChildNodes)) {
    if ($fragmentChild.LocalName -eq 'sectPr') { continue }
    $imported = $main.ImportNode($fragmentChild, $true)
    foreach ($blip in $imported.SelectNodes('.//a:blip', $mainNs)) {
        $oldId = $blip.GetAttribute('embed', $rUri)
        if ($relationshipMap.ContainsKey($oldId)) { $blip.SetAttribute('embed', $rUri, $relationshipMap[$oldId]) }
    }
    foreach ($docPr in $imported.SelectNodes('.//wp:docPr', $mainNs)) {
        $docPr.SetAttribute('id', [string]$drawingIndex)
        $drawingIndex++
    }
    foreach ($cNvPr in $imported.SelectNodes('.//pic:cNvPr', $mainNs)) {
        $cNvPr.SetAttribute('id', [string]$drawingIndex)
        $drawingIndex++
    }
    [void]$mainBody.InsertBefore($imported, $endNode)
}

function Save-Xml([xml]$document, [string]$path) {
    $settings = New-Object System.Xml.XmlWriterSettings
    $settings.Encoding = New-Object System.Text.UTF8Encoding($false)
    $settings.Indent = $true
    $settings.OmitXmlDeclaration = $false
    $writer = [System.Xml.XmlWriter]::Create($path, $settings)
    try { $document.Save($writer) } finally { $writer.Dispose() }
}

Save-Xml $main $mainXmlPath
Save-Xml $mainRels $mainRelsPath
Write-Output 'Bab 4 berhasil digabungkan ke paket DOCX.'
