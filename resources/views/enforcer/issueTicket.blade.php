@extends('components.app')

@section('title', 'Issue A Ticket')

@section('body')
    <h1 style="text-align: center">Enforcer Traffic Citation Ticket</h1>
    <form action="/enforcerTicket" method="POST">
        @csrf
        <Label>Apprehending Enforcer: {{ auth()->guard('enforcer')->user()->fname }} {{ auth()->guard('enforcer')->user()->lname }} </Label>    <br>
        <label for="name">To:</label>
        <input type="text" name="name" placeholder="enter full name" required><br>
        <label for="address">Address:</label>
        <textarea name="address" id="birthdate" placeholder="enter full address" id="" cols="30" rows="2"></textarea><br>
        <label for="address">Birthdate: </label>
        <input type="date" name="birthdate" placeholder="MM-DD-YYYY" required><br>
        <Label>License No.:</Label>
        <input type="text" name="license_num" id="license_num" placeholder="Enter license number" required><br>
        <label for="plate_num">Confiscated: </label><br>
        <select name="confiscated" id="confiscated">
            <option value="">None</option>
            <option value="License ID">License ID</option>
            <option value="Plate Number">Plate Number</option>
            <option value="ORCR">ORCR</option>
            <option value="TCT/TOP">TCT/TOP</option>
        </select><br>
        <label for="plate_num">License Plate: </label>
        <input type="text" name="plate_num" id="plate_num" placeholder="Enter plate number" required><br>
        <label for="vehicle_type">Vehicle Type: </label>
        <select name="vehicle_type" id="vehicle_type" required>
            <option value="">Select vehicle type</option>
            <option value="Motorcycle">Motorcycle</option>
            <option value="Tricycle">Tricycle</option>
            <option value="Truck">Truck</option>
            <option value="Sedan">Sedan</option>
            <option value="Bus">Bus</option>
            <option value="Jeepney">Jeepney</option>
            <option value="Van">Van</option>
            <option value="Closed Van">Closed Van</option>
            <option value="SUV">SUV</option>
            <option value="Pickup">Pickup</option>
        </select>
        <Label>Is the owner of the vehicle? </Label>
        <input type="checkbox" name="is_owner" id="is_owner_checkbox" value="1" checked><br>
        <Label>Owner name: </Label>
        <input type="text" name="owner_name" id="owner_name" placeholder="N/A if the violator is the owner" required><br>
        <br>
        <Label>Violation: </Label>
        @foreach ($violationList as $v)
        <br>
            <input type="checkbox" name="violations[]" id="violation_{{ $v->id }}" value="{{ $v->violation_code }}">
            <label for="violation_{{ $v->id }}" class="form-check-label">
                {{ $v->violation_name }} - ₱{{ $v->fine_amount }} - Penalty: {{ $v->penalty_points }} - Category: {{ $v->category }}
                - Description: {{ $v->description }}
            </label>
        @endforeach 
        <br>
        <label for="location">Location:</label>
        <input type="text" name="location" placeholder="Please enter location (Brgy, Street, etc.)" required><br>

        <button type="submit">Save</button>
    </form>
@endsection