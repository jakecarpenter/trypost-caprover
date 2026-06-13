@php
    // Maps each BrandVoiceTrait value to the instruction the model should follow.
    $voicePhrases = [
        // Point of view.
        'first_person' => 'Write in the first person (I / we), as the brand owner.',
        'second_person' => 'Address the reader directly in the second person (you).',
        'third_person' => "Write in the third person — report the topic, don't speak as the brand owner or claim its experiences as your own.",
        // Formality.
        'formal' => 'Keep a formal register.',
        'balanced' => 'Keep a balanced register — neither stiff nor slangy.',
        'casual' => 'Keep a casual, relaxed register.',
        // Energy.
        'calm' => 'Keep a calm, measured energy.',
        'moderate' => 'Keep a moderate energy — neither flat nor hyped.',
        'enthusiastic' => 'Bring upbeat, enthusiastic energy.',
        'vibrant' => 'Bring vibrant, high-energy delivery.',
        // Humor.
        'serious' => 'Stay serious and straight; no jokes.',
        'dry' => 'Use dry, subtle, understated humor.',
        'witty' => 'Add light wit where it fits.',
        'playful' => 'Be playful and fun.',
        // Attitude.
        'respectful' => 'Stay respectful and considerate.',
        'even_handed' => 'Stay even-handed and balanced in your stance.',
        'bold' => 'Take a clear, opinionated stance.',
        'provocative' => 'Be provocative — challenge conventions and play with provocation.',
        // Warmth.
        'neutral' => 'Keep an emotionally neutral, professional warmth.',
        'friendly' => 'Be warm and friendly.',
        'empathetic' => 'Be empathetic and understanding.',
        // Confidence.
        'humble' => 'Be humble and modest.',
        'confident' => 'Write with quiet confidence.',
        'assertive' => 'Be assertive and authoritative.',
        // Additive style traits.
        'direct' => 'Use direct, plain, accessible language.',
        'concise' => 'Keep sentences short and objective.',
        'transparent' => 'Be transparent — share behind-the-scenes details and real learnings.',
        'no_hype' => 'Avoid hype and overly promotional language.',
        'practical' => 'Focus on practical, actionable takeaways.',
        'data_driven' => 'Back claims with concrete results, numbers and specifics.',
        'storytelling' => 'Use narrative and storytelling.',
        'inspirational' => 'Be inspiring and motivating.',
        'educational' => 'Teach and explain clearly.',
        'technical' => 'Use precise, technical language for an expert audience.',
        'minimalist' => 'Use few or no emojis.',
    ];
@endphp
@foreach(($brand_voice_traits ?? []) as $trait)
@if(isset($voicePhrases[$trait]))
- {{ $voicePhrases[$trait] }}
@endif
@endforeach
