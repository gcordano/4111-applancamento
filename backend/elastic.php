<?php
require __DIR__ . '/vendor/autoload.php'; // Certifique-se de ajustar o caminho se necessário

use Elasticsearch\ClientBuilder;

$elasticClient = ClientBuilder::create()
    ->setHosts(['http://localhost:9200']) // Substitua pelo host do seu Elasticsearch
    ->build();
?>
