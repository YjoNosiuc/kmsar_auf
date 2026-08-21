<div class="research-card">
    <div class="ref">{{ $research->reference_number }}</div>
    <div class="rtitle">{{ $research->title }}</div>
    <div class="rmeta">
        <span>{{ $research->motherCollege->name ?? 'N/A' }}</span>
        <span>{{ $research->primaryAuthor->name ?? 'N/A' }}</span>
    </div>
</div>
