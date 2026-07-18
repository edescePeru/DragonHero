<?php
namespace App\Domain\Media\CatalogImages\Data;
final class CatalogImageUpload{private $data;public function __construct(array $data){$this->data=$data;}public function disk(){return$this->data['disk'];}public function root(){return$this->data['root'];}public function canonicalPath(){return$this->data['canonical_path'];}public function metadata(){return array_merge([],$this->data['metadata']);}}
