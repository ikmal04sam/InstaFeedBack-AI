<?php

return [
    'enabled' => env('ESSAY_RAG_ENABLED', true),
    'model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
    'timeout' => (int) env('OLLAMA_EMBEDDING_TIMEOUT', 30),
];
