<?php

return [
    'version' => 'uploaded_2026_11_10',
    'name' => 'Essay marking rubric',
    'component_maximum' => 20,
    'components' => [
        'task_fulfilment' => 'Task Fulfilment',
        'language' => 'Language',
        'organisation' => 'Organisation',
    ],
    'bands' => [
        [
            'descriptor_range' => [27, 30],
            'score_range' => [18, 20],
            'total_range' => [51, 60],
            'task_fulfilment' => 'Fulfils the task very competently with an excellent response. Develops ideas effectively, links information effectively, presents viewpoints effectively, and treats the topic maturely.',
            'language' => 'Very confident language control. Accurate apart from one or two slips; meaning is very clear. Uses a wide variety of sentence structures effectively, appropriate and varied vocabulary, and very effective links between ideas.',
            'organisation' => 'Paragraphs show clear unity and evidence of planning. Paragraphs are very coherent.',
        ],
        [
            'descriptor_range' => [23, 26],
            'score_range' => [16, 17],
            'total_range' => [41, 50],
            'task_fulfilment' => 'Fulfils the task competently with a good response. Develops ideas reasonably, presents viewpoints reasonably well, and treats the topic with reasonable maturity.',
            'language' => 'Reasonably confident language control. Largely accurate with few minor errors and generally accurate spelling; meaning is clear. Some variety of sentence structures, appropriate and varied vocabulary, and effective links between ideas.',
            'organisation' => 'Paragraphs have unity and show reasonable evidence of planning. Paragraphs are coherent.',
        ],
        [
            'descriptor_range' => [19, 22],
            'score_range' => [13, 15],
            'total_range' => [31, 40],
            'task_fulfilment' => 'Fulfils the task satisfactorily. Gives a satisfactory response, develops ideas satisfactorily, and links information. Makes a very modest attempt to present viewpoints.',
            'language' => 'Satisfactory language control (the sheet specifies no MWEs). Fairly accurate with some minor errors and few spelling errors; meaning comes across satisfactorily. Some sentence variety, but simple sentences prevail. Vocabulary is reasonably appropriate and varied; links ideas satisfactorily.',
            'organisation' => 'Paragraphs show some unity and evidence of planning. Reasonably coherent.',
        ],
        [
            'descriptor_range' => [15, 18],
            'score_range' => [10, 12],
            'total_range' => [21, 30],
            'task_fulfilment' => 'Fulfils the task in a limited way with a limited response. Hardly develops ideas; information is poorly linked. Hardly attempts to present viewpoints or treat the topic maturely.',
            'language' => 'Modest language control, with more SWEs than MWEs. Language is sometimes accurate but errors are more serious, with some spelling errors. Meaning may occasionally be unclear but remains comprehensible. Limited sentence variety, generally simple structures, mainly simple and modestly appropriate vocabulary. Attempts to link ideas are not quite satisfactory.',
            'organisation' => 'Paragraphs show a little unity but lack planning.',
        ],
        [
            'descriptor_range' => [11, 14],
            'score_range' => [8, 9],
            'total_range' => [11, 20],
            'task_fulfilment' => 'Fulfils the task in a limited way with a limited response. Hardly develops ideas; information is poorly linked. Hardly attempts to present viewpoints or treat the topic maturely.',
            'language' => 'Poor language control, with high MWEs or groping. Largely inaccurate language with more spelling errors. Meaning is sometimes blurred, making reading difficult. Hardly any sentence variety; mainly simple structures. Hardly any appropriate or varied vocabulary, and hardly any attempt to link ideas.',
            'organisation' => 'Some paragraphing is evident, but it does not show unity or planning. Hardly any coherence.',
        ],
        [
            'descriptor_range' => [0, 10],
            'score_range' => [0, 7],
            'total_range' => [0, 10],
            'task_fulfilment' => 'Does not fulfil the task. Shows a very limited response or merely mentions the stimulus. Makes no attempt to present viewpoints and treats the topic shallowly and immaturely.',
            'language' => 'Very poor language control. Grossly inaccurate language with serious spelling errors. Meaning is often unclear and incomprehensible. No sentence variety, inappropriate and unvaried vocabulary, and no attempt to link ideas.',
            'organisation' => 'Paragraphing may be haphazard or nonexistent. No coherence.',
        ],
    ],
];
