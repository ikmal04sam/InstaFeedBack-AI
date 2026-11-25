<?php

$bundledReferencePath = base_path('AI FYP/muet_raft_train_argon.jsonl');

return [
    'enabled' => env('ESSAY_RAG_ENABLED', true),
    'model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
    'timeout' => (int) env('OLLAMA_EMBEDDING_TIMEOUT', 30),
    'reference_path' => env('ESSAY_RAG_REFERENCE_PATH', is_file($bundledReferencePath) ? $bundledReferencePath : null),
];
