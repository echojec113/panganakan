<x-guest-layout>

    <h2 class="text-2xl font-bold text-center mb-6 text-gray-700">
        Create Account
    </h2>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <!-- Name -->
        <div>
            <label class="block text-sm font-medium">Name</label>
            <input type="text" name="name" required
                class="w-full mt-1 p-2 border rounded focus:ring focus:ring-blue-300">
        </div>

        <!-- Email -->
        <div>
            <label class="block text-sm font-medium">Email</label>
            <input type="email" name="email" required
                class="w-full mt-1 p-2 border rounded focus:ring focus:ring-blue-300">
        </div>

        <!-- Password -->
        <div>
            <label class="block text-sm font-medium">Password</label>
            <input type="password" name="password" required
                class="w-full mt-1 p-2 border rounded focus:ring focus:ring-blue-300">
        </div>

        <!-- Confirm -->
        <div>
            <label class="block text-sm font-medium">Confirm Password</label>
            <input type="password" name="password_confirmation" required
                class="w-full mt-1 p-2 border rounded focus:ring focus:ring-blue-300">
        </div>

        <!-- Button -->
        <button type="submit"
            class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
            Register
        </button>

        <p class="text-center text-sm">
            Already have an account?
            <a href="{{ route('login') }}" class="text-blue-500">Login</a>
        </p>

    </form>

</x-guest-layout>