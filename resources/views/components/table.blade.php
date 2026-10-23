<div class="overflow-x-auto">
    <table {{ $attributes->class('w-full text-left text-sm whitespace-nowrap [&_td]:border-t [&_td]:border-line [&_td]:px-3 [&_td]:py-2.5 [&_th]:bg-brand/10 [&_th]:px-3 [&_th]:py-2.5 [&_th]:font-semibold [&_th]:text-brand [&_tbody_tr:hover]:bg-brand/5') }}>
        {{ $slot }}
    </table>
</div>
