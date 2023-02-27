<div>
    <hr />
    @foreach ($menu as $navItem)
    @if(Route::has($navItem->route))
    @empty($navItem->roles ?? '')
    <x-menu-item title="{{ $navItem->name }}" route="{{ $navItem->route }}">
        {{ svg($navItem->icon)->class("w-5 h-5") }}
    </x-menu-item>
    @else
    @hasanyrole($navItem->roles)
    <x-menu-item title="{{ $navItem->name }}" route="{{ $navItem->route }}">
        {{ svg($navItem->icon)->class("w-5 h-5") }}
    </x-menu-item>
    @endhasanyrole
    @endempty
    @endif
    @endforeach
</div>
