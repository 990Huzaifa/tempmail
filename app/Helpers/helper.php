<?php


function calculateMailSize($text, $attachments = []) {
    $size = strlen($text); // Body ka size
    foreach ($attachments as $file) {
        $size += $file->getSize(); // Attachment ka size 
    }
    return $size;  // in bytes
}

function formatModoboaRecord($rawRecord, $type = 'DKIM')
{
    if ($type === 'DKIM') {
        // 1. Brackets (), double quotes ", aur new lines/spaces khatam karein
        $clean = str_replace(['(', ')', '"', "\n", "\r", " ", "\t"], '', $rawRecord);
        
        // 2. Agar Modoboa "v=DKIM1;k=rsa;p=" bhej raha hai to theek, 
        // warna ensure karein ke string prefix sahi ho
        return $clean;
    }

    if ($type === 'SPF') {
        // SPF mein sirf extra quotes hatani hoti hain, spaces rehne deni hain
        return trim(str_replace('"', '', $rawRecord));
    }

    return trim($rawRecord);
}