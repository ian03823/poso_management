@extends('components.layout')

@section('title', 'Add Violation')
@section('content')
<div class="container mt-4">
    <h2 class="mb-3">Add New Violation</h2>

    <form action="/violation" method="POST">
        @csrf
        <div class="mb-3">
            <label for="violation_code" class="form-label">Violation Code</label>
            <input type="text" class="form-control" id="violation_code" name="violation_code" required>
        </div>

        <div class="mb-3">
            <label for="violation_name" class="form-label">Violation Name</label>
            <input type="text" class="form-control" id="violation_name" name="violation_name" required>
        </div>

        <div class="mb-3">
            <label for="fine_amount" class="form-label">Fine Amount (₱)</label>
            <input type="number" class="form-control" id="fine_amount" name="fine_amount" step="0.01" required>
        </div>

        <div class="mb-3">
            <label for="penalty_points" class="form-label">Penalty Points</label>
            <input type="number" class="form-control" id="penalty_points" name="penalty_points" min="0" required>
        </div>
        <div class="mb-3">
            <label for="penalty_points" class="form-label">Description</label>
            <input type="text" class="form-control" id="descri  ption" name="description" required>
        </div>

        <div class="mb-3">
            <label for="category" class="form-label">Category</label>
            <select class="form-control" id="category" name="category" required>
                <option value="Moving Violations">Moving Violations</option>
                <option value="Non-Moving Violations">Non-Moving Violations</option>
                <option value="Safety Violations">Safety Violations</option>
                <option value="Parking Violations">Parking Violations</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Add Violation</button>
    </form>
</div>

@endsection
