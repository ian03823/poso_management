@extends('components.layout')

@section('title', 'Add Enforcer')

@section('content')
<div class="main-content flex justify-center items-center">
    <div class="form-container">
        <h2 class="text-xl font-semibold mb-4">Add Enforcer</h2>
        <button type="button" class="btn btn-secondary" id="previousBtn">Previous</button>
        @if(session('success')) 
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="/enforcer" method="POST"  id="enforcerForm">
            @csrf
            <div class="mb-3">
                <label for="badge" class="block font-medium">Badge:</label>
                <input type="text" class="border border-gray-300 rounded p-2 w-full required-field" id="badge_num" name="badge_num" 
                autocomplete="off" value="{{old('badge_num')}}">
            </div>
            
            <div class="mb-3">
                <label for="fname" class="block font-medium">First Name</label>
                <input type="text" class="border border-gray-300 rounded p-2 w-full required-field" id="fname" name="fname"
                autocomplete="off" value="{{old('fname')}}">
            </div>

            <div class="mb-3">
                <label for="mname" class="block font-medium">Middle Name</label>
                <input type="text" class="border border-gray-300 rounded p-2 w-full required-field" id="mname" name="mname"
                autocomplete="off" value="{{old('mname')}}">
            </div>

            <div class="mb-3">
                <label for="lname" class="block font-medium">Last Name</label>
                <input type="text" class="border border-gray-300 rounded p-2 w-full required-field" id="lname" name="lname"
                autocomplete="off" value="{{old('lname')}}">
            </div>

            <div class="mb-3">
                <label for="phone" class="block font-medium">Phone</label>
                <input type="tel" class="border border-gray-300 rounded p-2 w-full required-field" id="phone" name="phone"
                autocomplete="off" value="{{old('phone')}}">
            </div>

            <div class="mb-3">
                <label for="password" class="block font-medium">Password</label>
                <div class="flex">
                    <input type="text" class="border border-gray-300 rounded p-2 w-full required-field" id="password" name="password"
                    autocomplete="off" value="{{old('password')}}">
                    <button type="button" class="ml-2 bg-gray-700 text-white px-3 py-2 rounded" onclick="generatePassword()">Generate</button>
                </div>
            </div>  

            <button type="submit" class="bg-green-700 text-white px-4 py-2 rounded hover:bg-green-800">Add Enforcer</button>
        </form>
    </div>
</div>

<script>
    function generatePassword() {
        let prefix = "posoenforcer_";
        let randomNumber = Math.floor(100 + Math.random() * 900);
        let password = prefix + randomNumber;
        document.getElementById("password").value = password;
    }
    document.getElementById('previousBtn').addEventListener('click', function(event) {
        let inputs = document.querySelectorAll('input');
        let emptyFields = [];
        let isEmpty = true;
        
        // Reset field borders
        inputs.forEach(input => {
            input.style.border = "1px solid #ced4da"; // Reset border color
            if (input.value.trim() === "") {
                emptyFields.push(input);
            } else {
                isEmpty = false; // At least one field has a value
            }
        });

        // If all fields are empty
        if (emptyFields.length === inputs.length) {
            event.preventDefault();
            emptyFields.forEach(input => input.style.border = "2px solid red");
            
            Swal.fire({
                title: "All fields are empty!",
                text: "Please fill out the form before proceeding.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, leave",
                cancelButtonText: "Stay"
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "/enforcer"; // Redirect only if confirmed
                }
            });

            return;
        }

        // If some fields are empty
        if (emptyFields.length > 0) {
            event.preventDefault();
            emptyFields.forEach(input => input.style.border = "2px solid red");

            Swal.fire({
                title: "Form is not complete!",
                text: "Are you sure you want to leave? Some fields are still empty.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, leave",
                cancelButtonText: "Stay"
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "/enforcer"; // Redirect only if confirmed
                }
            });
            return;
        }

        // If everything is filled, go back normally
        window.history.back();
    });
</script>

@endsection