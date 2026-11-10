<?php

return [
    'tesseract_path' => env('TESSERACT_PATH', 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'),
    'tessdata_path' => env('TESSERACT_DATA_PATH', 'C:\\Program Files\\Tesseract-OCR\\tessdata'),
    'languages' => env('OCR_LANGUAGES', 'amh+eng'),
    'timeout' => (int) env('OCR_TIMEOUT', 120),
];
