@extends('components.layout')
@section('title', 'Violation List')
@section('content')
<div class="container mt-4">
    <h2 class="mb-3">Violation List</h2>
    <a href="violation/create" class="btn btn-primary mb-3">Add Violation</a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Fine Amount</th>
                <th>Penalty Points</th>
                <th>Category</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($violation as $violations)
                <tr>
                    <td>{{ $violations->violation_code }}</td>
                    <td>{{ $violations->violation_name }}</td>
                    <td>₱{{ number_format($violations->fine_amount, 2) }}</td>
                    <td>{{ $violations->penalty_points }}</td>
                    <td>{{ $violations->category }}</td>
                    <td>
                        <a href="{{ route('violation.edit', $violations->id) }}" class="btn btn-warning btn-sm">Edit</a>
                        <form action="violation/{{$violations->id}}" method="POST" onsubmit="return confirm('Are you sure you want to delete?');" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
