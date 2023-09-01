<?php

interface ConvertableFile
{
    public function convert(string $file, string $fileName, string $destinationDir);
}

class PngConverter implements ConvertableFile
{
    public function convert(string $file, string $fileName, string $destinationDir = __DIR__ . "/processed-png/", int $quality = 85): void
    {
        $webpFile = $destinationDir . $fileName . '.webp';

        // Load the PNG image
        $image = imagecreatefrompng($file);

        // Get image dimensions
        $width = imagesx($image);
        $height = imagesy($image);

        // Create a true-color image with transparency support
        $trueColorImage = imagecreatetruecolor($width, $height);
        imagealphablending($trueColorImage, false);
        imagesavealpha($trueColorImage, true);

        // Fill the true-color image with transparent background
        $transparentColor = imagecolorallocatealpha($trueColorImage, 0, 0, 0, 127);
        imagefill($trueColorImage, 0, 0, $transparentColor);

        // Copy the transparency information
        imagecopyresampled($trueColorImage, $image, 0, 0, 0, 0, $width, $height, $width, $height);

        // Convert and save as WebP
        imagewebp($trueColorImage, $webpFile, $quality); // 85 is the quality value (0 to 100)

        // Free up memory
        imagedestroy($image);
        imagedestroy($trueColorImage);
    }
}

class JpegConverter implements ConvertableFile
{
    public function convert(string $file, string $fileName, string $destinationDir = __DIR__ . "/processed-jpeg/", int $quality = 85): void
    {
        $outputPath = $destinationDir . $fileName . ".webp";
        $jpegImage = imagecreatefromjpeg($file);

        // Convert and save as WebP
        imagewebp($jpegImage, $outputPath, $quality);

        // Free up memory
        imagedestroy($jpegImage);
    }
}

class Converter
{
    const MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png'
    ];

    private string $file;

    private string $fileName;

    private string $mime_type;

    private JpegConverter|PngConverter|null $fileConverter;

    private int $quality = 85;

    /**
     * @throws Exception
     */
    public function processFile(string $file, int $quality = 85): string
    {
        $this->file = $file;

        $this
            ->loadFileName()
            ->checkFileProcessable()
            ->selectFileConverter();

        if ($this->fileConverter === null) {
            throw new Exception("File $this->fileName with mime/type $this->mime_type is not supported by convertors");
        }

        $this->fileConverter->convert(
            file: $this->file,
            fileName: $this->fileName,
            quality: $quality
        );

        return $this->fileName;
    }

    private function loadFileName(): static
    {
        $this->fileName = pathinfo($this->file, PATHINFO_FILENAME);
        return $this;
    }

    /**
     * @throws Exception
     */
    private function checkFileProcessable(): Converter|Exception
    {
        $this->mime_type = mime_content_type($this->file);

        if (in_array($this->mime_type, self::MIME_TYPES)) {
            return $this;
        }

        throw new Exception("File $this->fileName is not convertible by mime type $this->mime_type");
    }

    /**
     * factory pattern for selecting correct convertor
     * @return void
     */
    private function selectFileConverter(): void
    {
        $this->fileConverter = match ($this->mime_type) {
            'image/jpeg', 'image/jpg' => new JpegConverter(),
            'image/png' => new PngConverter(),
            default => null
        };
    }
}

// Source directory containing images
$sourceDir = __DIR__ . "/images/";
// Get an array of PNG files from the source directory
$rawFiles = glob($sourceDir . '*');

$converter = new Converter();
list($key, $val) = explode('=', $argv[1]);
$quality = ($key === '--qlt') ? $val : 85;
print_r("/************* FILE CONVERTER *************/\n");
foreach ($rawFiles as $rawFile) {
    try {
        $fileName = $converter->processFile($rawFile, $quality);
        print_r("File $fileName processed\n");
    } catch (Exception $e) {
        print_r($e->getMessage() . "\n");
    }
}