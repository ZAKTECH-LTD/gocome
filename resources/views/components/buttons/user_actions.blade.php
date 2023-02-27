<div class="flex items-center gap-x-2">

    @if ($model->id != \Auth::id())
    <x-buttons.show :model="$model" />
    @hasanyrole('admin')
        @if($model->hasAnyRole('city-admin'))
            <x-buttons.assign :model="$model" />
        @endif
    @endhasanyrole
    <x-buttons.edit :model="$model" />
    @if( $model->is_active )
        <x-buttons.deactivate :model="$model" />
    @else
        <x-buttons.activate :model="$model" />
    @endif

    <x-buttons.delete :model="$model" />

    @else
        <span class="text-xs italic font-thin text-gray-400">{{ __("Current Account") }}</span>
    @endif

</div>
