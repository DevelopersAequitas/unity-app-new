<table>
    <thead><tr>@foreach($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach($rows as $log)
        <tr>
            <td>{{ $log->id }}</td><td>{{ $log->to_email }}</td><td>{{ $log->to_name }}</td><td>{{ $log->subject }}</td>
            <td>{{ $log->template_key }}</td><td>{{ $log->source_module }}</td><td>{{ $log->status }}</td>
            <td>{{ optional($log->sent_at)->toDateTimeString() }}</td><td>{{ optional($log->created_at)->toDateTimeString() }}</td>
            <td>{{ $log->triggered_by }}</td><td>{{ $log->trigger_user_name ?: $log->trigger_user_email }}</td><td>{{ $log->mail_provider }}</td><td>{{ $log->error_message }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
