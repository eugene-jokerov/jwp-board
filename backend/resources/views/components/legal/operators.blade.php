@props([
    // Показывать ли почту для обращений: в соглашении она уже есть в реквизитах.
    'withContact' => true,
])

@php
    $legal = config('legal');
    $appUrl = rtrim((string) $legal['app_url'], '/');

    // Незаполненные и null-значения показываются пометкой: пустая ячейка в
    // юридическом документе выглядит как ошибка вёрстки, а не как забытый реквизит.
    $fields = [
        'Наименование' => $legal['operator']['name'] ?? null,
        'ИНН' => $legal['operator']['inn'] ?? null,
        'ОГРН / ОГРНИП' => $legal['operator']['ogrn'] ?? null,
        'Адрес' => $legal['operator']['address'] ?? null,
    ];

    if ($withContact) {
        $fields['Электронная почта для обращений'] = $legal['contact_email'] ?? null;
    }
@endphp

<div {{ $attributes->merge(['class' => 'operators']) }}>
  <dl>
    @foreach ($fields as $label => $value)
      <dt>{{ $label }}</dt>
      <dd>{{ filled($value) ? $value : 'не указан' }}</dd>
    @endforeach
  </dl>
</div>
