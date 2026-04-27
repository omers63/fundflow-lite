@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
    $isRtl = app()->getLocale() === 'ar';
    $effectiveAlign = ($isRtl && $align === 'center') ? 'right' : $align;
@endphp
                <table class="action" align="{{ $effectiveAlign }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td align="{{ $effectiveAlign }}">
                            <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td align="{{ $effectiveAlign }}">

                                                                       <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                            <tr>
                        <td>
                    <a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener">{!! $slot !!}</a>
                </td>
            </tr>
        </table>
    </td>
</tr>
</table>
</td>
</tr>
</table>
