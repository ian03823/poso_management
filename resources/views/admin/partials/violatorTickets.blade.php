<div id="violator-table">
<div class="table-responsive">
    @if($active->isEmpty())
        <p>No active tickets.</p>
    @else
    <table class="table table-bordered table-hover">
      <thead>
        <tr>
          <th class="text-center">#</th>
          <th class="text-center">Issued</th>
          <th class="text-center">Vehicle</th>
          <th class="text-center">Violation(s)</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>
        @foreach($active as $t)
          <tr>
            <td>{{ $t->id }}</td>
            <td>{{ $t->issued_at->format('d M Y, H:i') }}</td>
            <td>{{ optional($t->vehicle)->plate_number ?? '—' }}</td>
            <td>{{ $t->violation_names }}</td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-primary" disabled>
                    {{ optional($t->status)->name ?? 'Unknown' }}
                </button>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
</div>