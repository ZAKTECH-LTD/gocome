@if (!empty($model))
<a href="{{ route('users.details',$model->id) }}" class="hover:underline text-primary-600"> {{ $value ?? $model->name ?? $model[$column->attribute] ??  '' }}</a>
@endif
