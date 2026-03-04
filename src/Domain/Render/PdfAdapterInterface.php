<?php

namespace CargoDocsStudio\Domain\Render;

interface PdfAdapterInterface
{
    /**
     * @return array{success:bool,file_path?:string,file_url?:string,error?:string}
     */
    public function render(string $html, string $filename, array $options = []): array;
}
