@php
    $emojiCategories = [
        'recent' => [
            ['📝', 'note writing journal'], ['✅', 'check done event'], ['🖼️', 'picture image photo'], ['💬', 'chat message'],
            ['🤖', 'assistant robot'], ['🎨', 'art generated image'], ['📎', 'attachment media'], ['⭐', 'star favorite'],
        ],
        'smileys' => [
            ['😀', 'grinning happy smile'], ['😊', 'smiling warm happy'], ['😂', 'laughing tears joy'], ['🥰', 'love affection hearts'],
            ['😌', 'relieved calm'], ['😎', 'cool sunglasses'], ['🤔', 'thinking question'], ['😴', 'sleep tired'],
            ['😤', 'frustrated angry'], ['😢', 'sad crying'], ['🥳', 'party celebration'], ['🤩', 'excited star eyes'],
        ],
        'people' => [
            ['👍', 'thumbs up yes'], ['👎', 'thumbs down no'], ['👏', 'clap applause'], ['🙏', 'thanks prayer please'],
            ['💪', 'strong exercise muscle'], ['🧘', 'yoga meditation calm'], ['🏃', 'run exercise'], ['🚶', 'walk walking'],
            ['🙋', 'raise hand'], ['🤝', 'handshake agreement'], ['👀', 'eyes watch'], ['🧠', 'brain think mental'],
        ],
        'animals' => [
            ['🐶', 'dog pet'], ['🐕', 'dog walking'], ['🐱', 'cat pet'], ['🐾', 'paw pets'],
            ['🐦', 'bird'], ['🐠', 'fish'], ['🦋', 'butterfly'], ['🐝', 'bee'],
            ['🌱', 'seedling growth'], ['🌻', 'sunflower'], ['🌳', 'tree nature'], ['🌈', 'rainbow'],
        ],
        'food' => [
            ['☕', 'coffee drink'], ['💧', 'water hydration'], ['🍎', 'apple healthy food'], ['🥗', 'salad healthy meal'],
            ['🍽️', 'meal food plate'], ['🍕', 'pizza'], ['🍰', 'cake dessert'], ['🥕', 'carrot vegetable'],
            ['💊', 'medicine medication pill'], ['🫖', 'tea pot'], ['🥤', 'drink cup'], ['🍪', 'cookie snack'],
        ],
        'activities' => [
            ['🎯', 'target goal'], ['🏆', 'trophy win'], ['🏋️', 'weights workout'], ['🚴', 'cycling bike'],
            ['🎵', 'music song'], ['🎮', 'game'], ['📚', 'books reading'], ['✍️', 'writing'],
            ['💼', 'work briefcase'], ['📅', 'calendar schedule'], ['⏰', 'alarm time'], ['🔥', 'fire streak'],
        ],
        'travel' => [
            ['🏠', 'home house'], ['🚗', 'car drive'], ['✈️', 'plane travel'], ['🚀', 'rocket space'],
            ['🌍', 'world earth'], ['🏖️', 'beach vacation'], ['⛰️', 'mountain hike'], ['🧭', 'compass direction'],
            ['☀️', 'sun sunny'], ['🌙', 'moon night'], ['☁️', 'cloud weather'], ['⚡', 'lightning energy'],
        ],
        'symbols' => [
            ['❤️', 'heart love'], ['💚', 'green heart health'], ['💙', 'blue heart'], ['💜', 'purple heart'],
            ['✨', 'sparkles special'], ['💡', 'idea light'], ['⚠️', 'warning alert'], ['❗', 'important exclamation'],
            ['📌', 'pin important'], ['🔔', 'bell reminder'], ['🔒', 'lock private'], ['♻️', 'repeat recycle'],
        ],
    ];
    $pickerLabel = $label ?? 'Emoji';
    $pickerValue = $value ?? '📝';
@endphp

<div id="{{ $pickerId }}-field" class="relative {{ $containerClass ?? '' }}" data-emoji-picker>
    <label class="label" for="{{ $pickerId }}-input">{{ $pickerLabel }}</label>
    <input id="{{ $pickerId }}-input" type="hidden" name="{{ $name ?? 'emoji' }}" value="{{ $pickerValue }}" data-emoji-input>
    <button type="button" class="input flex items-center gap-3 text-left" data-emoji-toggle aria-expanded="false" aria-controls="{{ $pickerId }}-menu">
        <span class="text-2xl" data-emoji-preview aria-hidden="true">{{ $pickerValue }}</span>
        <span class="min-w-0 flex-1 text-sm font-semibold">Choose emoji</span>
        <span class="text-xs text-slate-400" aria-hidden="true">▼</span>
    </button>
    <div id="{{ $pickerId }}-menu" class="absolute inset-x-0 top-full z-[90] mt-2 hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900" data-emoji-menu>
        <div id="{{ $pickerId }}-search-control" class="border-b border-slate-200 p-3 dark:border-slate-700">
            <label class="sr-only" for="{{ $pickerId }}-search">Search emojis</label>
            <input id="{{ $pickerId }}-search" type="search" class="input text-sm" placeholder="Search emojis…" autocomplete="off" data-emoji-search>
        </div>
        <div id="{{ $pickerId }}-category-tabs" class="flex gap-1 overflow-x-auto border-b border-slate-200 p-2 dark:border-slate-700" role="tablist" aria-label="Emoji categories">
            @foreach(array_keys($emojiCategories) as $category)
                <button type="button" class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold capitalize {{ $loop->first ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}" data-emoji-category="{{ $category }}">{{ $category }}</button>
            @endforeach
        </div>
        <div id="{{ $pickerId }}-emoji-grid" class="grid max-h-64 grid-cols-6 gap-1 overflow-y-auto p-3" data-emoji-grid>
            @foreach($emojiCategories as $category => $emojis)
                @foreach($emojis as [$emoji, $searchTerms])
                    <button type="button" class="flex aspect-square items-center justify-center rounded-xl text-2xl transition hover:bg-indigo-100 focus:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:bg-indigo-950 dark:focus:bg-indigo-950 {{ $category === 'recent' ? '' : 'hidden' }}" data-emoji-option data-emoji-category-name="{{ $category }}" data-emoji-name="{{ $searchTerms }}" data-emoji-value="{{ $emoji }}" aria-label="{{ $searchTerms }}">{{ $emoji }}</button>
                @endforeach
            @endforeach
        </div>
        <p id="{{ $pickerId }}-empty-message" class="hidden px-3 pb-3 text-center text-sm text-slate-500" data-emoji-empty>No emojis match that search.</p>
    </div>
</div>
