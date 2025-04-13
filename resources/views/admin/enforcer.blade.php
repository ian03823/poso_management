@extends('components.layout')
@section('title', 'Enforcer List')
@section('content')
<div class="main-content flex justify-center items-center">
    <div class="form-container w-full">
        <h2 class="text-xl font-semibold mb-4">Enforcer List</h2>
        <a href="enforcer/create">Add Enforcer</a>

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

        <div class="overflow-x-auto">
            <table class="w-full border border-gray-300 text-left">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="p-2 border border-gray-300">Badge No.</th>
                        <th class="p-2 border border-gray-300">First Name</th>
                        <th class="p-2 border border-gray-300">Middle Name</th>
                        <th class="p-2 border border-gray-300">Last Name</th>
                        <th class="p-2 border border-gray-300">Phone</th>
                        <th class="p-2 border border-gray-300">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($enforcer as $e)
                    <tr class="hover:bg-gray-100">
                        <td class="p-2 border border-gray-300">{{ $e->badge_num }}</td>
                        <td class="p-2 border border-gray-300">{{ $e->fname }} </td>
                        <td class="p-2 border border-gray-300">{{ $e->mname }}</td>
                        <td class="p-2 border border-gray-300">{{ $e->lname }}</td>
                        <td class="p-2 border border-gray-300">{{ $e->phone }}</td>
                        <td class="p-2 border border-gray-300 flex space-x-2">
                            <a href="enforcer/{{$e->id}}/edit" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">Edit</a>
                            <form action="enforcer/{{$e->id}}" method="POST" onsubmit="return confirm('Are you sure you want to delete this enforcer?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach

                    @if($enforcer->isEmpty())
                    <tr>
                        <td colspan="7" class="text-center p-4 border border-gray-300">No enforcers found.</td>
                    </tr>
                    @endif
                </tbody>
            </table>
            {{$enforcer->links()}}
        </div>
    </div>
</div>

@endsection