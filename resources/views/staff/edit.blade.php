<x-app-layout>
    <div class="p-8 max-w-xl mx-auto">
        <a href="{{ route('staff.index') }}" 
   class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-4">
    <span>←</span>
    <span>Back to Manage Staff</span>
</a>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <x-app-header title="Edit Staff" class="mb-6" />

            <form id="editStaffForm" action="{{ route('staff.update', $staff) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" value="{{ $staff->name }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" value="{{ $staff->email }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <button type="submit" class="btn btn-primary">
                    Update
                </button>
            </form>
        </div>

    </div>

    <!-- Modal -->
    <div id="popupModal" class="fixed inset-0 backdrop-blur-md bg-black/20 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-lg p-6 w-80 text-center">
            <h2 id="modalTitle" class="text-lg font-bold mb-2"></h2>
            <p id="modalMessage" class="text-sm text-gray-600 mb-4"></p>

            <div id="modalButtons" class="flex justify-center gap-2"></div>
        </div>
    </div>

    <!-- Script -->
    <script>
        const form = document.getElementById('editStaffForm');
        const modal = document.getElementById('popupModal');
        const title = document.getElementById('modalTitle');
        const message = document.getElementById('modalMessage');
        const buttons = document.getElementById('modalButtons');

        function showModal(modalTitle, modalMessage, btns) {
            title.innerText = modalTitle;
            message.innerText = modalMessage;
            buttons.innerHTML = '';

            btns.forEach(btn => {
                let button = document.createElement('button');
                button.innerText = btn.text;
                button.className = btn.class;
                button.onclick = btn.action;
                buttons.appendChild(button);
            });

            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        // FORM SUBMIT
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            let name = form.name.value.trim();
            let email = form.email.value.trim();

            // VALIDATION
            if (!name || !email) {
                showModal(
                    "Validation Error",
                    "Please fill out all required staff details.",
                    [{
                        text: "OK",
                        class: "bg-blue-600 text-white px-4 py-2 rounded-lg",
                        action: closeModal
                    }]
                );
                return;
            }

            // CONFIRMATION
            showModal(
                "Confirm Update",
                "Are you sure you want to update this staff?",
                [
                    {
                        text: "Cancel",
                        class: "px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100",
                        action: closeModal
                    },
                    {
                        text: "Update",
                        class: "px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700",
                        action: () => {
                            form.submit();
                        }
                    }
                ]
            );
        });
    </script>

</x-app-layout>