<x-app-layout>
    <div class="p-8 max-w-xl mx-auto">
        <a href="{{ route('staff.index') }}" 
   class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-4">
    <span>←</span>
    <span>Back to Manage Staff</span>
</a>
        <div class="bg-white p-6 rounded-2xl shadow border">
            <h1 class="text-xl font-bold mb-4">Edit Staff</h1>

            <form id="editStaffForm" action="{{ route('staff.update', $staff) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-sm">Name</label>
                    <input type="text" name="name" value="{{ $staff->name }}" class="w-full border rounded-lg p-2 mt-1">
                </div>

                <div>
                    <label class="text-sm">Email</label>
                    <input type="email" name="email" value="{{ $staff->email }}" class="w-full border rounded-lg p-2 mt-1">
                </div>

                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Update
                </button>
            </form>
        </div>

    </div>

    <!-- 🔥 MODAL -->
    <div id="popupModal" class="fixed inset-0 backdrop-blur-md bg-black/20 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-lg p-6 w-80 text-center">
            <h2 id="modalTitle" class="text-lg font-bold mb-2"></h2>
            <p id="modalMessage" class="text-sm text-gray-600 mb-4"></p>

            <div id="modalButtons" class="flex justify-center gap-2"></div>
        </div>
    </div>

    <!-- 🔥 SCRIPT -->
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

        // 🚀 FORM SUBMIT
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            let name = form.name.value.trim();
            let email = form.email.value.trim();

            // ❌ VALIDATION
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

            // ⚠️ CONFIRMATION
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