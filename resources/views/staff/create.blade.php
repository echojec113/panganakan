<x-app-layout>
    <style>
        .add-staff-theme {
            --staff-primary: #55B85A;
            --staff-primary-hover: #4aa04c;
            --staff-border: #E7E9E5;
            --staff-text: #19355F;
            --staff-muted: #657083;
        }
        .add-staff-theme .staff-card {
            border-color: var(--staff-border);
        }
        .add-staff-theme .staff-input:focus {
            border-color: var(--staff-primary);
            --tw-ring-color: var(--staff-primary);
        }
        .add-staff-theme .staff-label { color: var(--staff-text); }
        .add-staff-theme .staff-back { color: var(--staff-muted); }
        .add-staff-theme .staff-back:hover { color: var(--staff-primary-hover); }
       .add-staff-theme .staff-modal {
           border: 1px solid var(--staff-border);
       }
       .add-staff-theme .staff-modal-title {
           color: var(--staff-text);
       }
       .add-staff-theme .staff-modal-message {
           color: var(--staff-muted);
       }
       .add-staff-theme .staff-save {
           background-color: var(--staff-primary);
           border: 1px solid var(--staff-primary);
           border-radius: 0.75rem;
           box-shadow: 0 1px 2px rgba(55, 126, 75, 0.12);
           font-weight: 600;
       }
       .add-staff-theme .staff-save:hover {
           background-color: var(--staff-primary-hover);
           border-color: var(--staff-primary-hover);
       }
       .add-staff-theme .staff-save:active {
           background-color: #438f46;
           border-color: #438f46;
       }
       .add-staff-theme .staff-save:focus-visible {
           outline: none;
           box-shadow: 0 0 0 3px rgba(85, 184, 90, 0.3);
       }
   </style>

   <div class="add-staff-theme min-h-screen bg-[#FCFBF8] px-4 py-8 sm:px-6 lg:px-8">
       <div class="mx-auto max-w-xl">
           <a href="{{ route('staff.index') }}"
              class="staff-back mb-6 inline-flex items-center gap-2 text-sm font-medium transition">
               <span aria-hidden="true">←</span>
               <span>Back to Manage Staff</span>
           </a>

           <div class="staff-card overflow-hidden rounded-2xl border bg-white shadow-sm">
               <div class="border-b border-gray-100 bg-[#F6F4EE] px-6 py-5">
                   <x-app-header
                       title="Add Staff"
                       subtitle="Create a new clinic staff account."
                   />
               </div>

               <form id="staffForm" action="{{ route('staff.store') }}" method="POST" class="space-y-5 p-6">
                   @csrf

                   <div>
                       <label class="staff-label block text-sm font-medium">Name</label>
                       <input type="text" name="name" class="staff-input mt-1 block h-10 w-full rounded-lg border border-gray-300 px-3 text-sm text-gray-700 transition focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A]">
                   </div>

                   <div>
                       <label class="staff-label block text-sm font-medium">Email</label>
                       <input type="email" name="email" class="staff-input mt-1 block h-10 w-full rounded-lg border border-gray-300 px-3 text-sm text-gray-700 transition focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A]">
                   </div>


                   <div>
                       <label class="staff-label block text-sm font-medium">Password</label>
                       <input type="password" name="password" class="staff-input mt-1 block h-10 w-full rounded-lg border border-gray-300 px-3 text-sm text-gray-700 transition focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A]">
                   </div>

                   <div class="flex justify-end border-t border-gray-100 pt-5">
                       <button type="submit" class="btn staff-save">
                           Save
                       </button>
                   </div>
               </form>
           </div>
       </div>
   </div>

    <!-- Modal -->
    <div id="popupModal" class="fixed inset-0 backdrop-blur-md bg-black/10 flex items-center justify-center hidden z-50">
        <div class="staff-modal w-80 rounded-2xl bg-white p-6 text-center shadow-xl">
            <h2 id="modalTitle" class="staff-modal-title mb-2 text-lg font-bold"></h2>
            <p id="modalMessage" class="staff-modal-message mb-4 text-sm"></p>

            <div id="modalButtons" class="flex justify-center gap-2"></div>
        </div>
    </div>

    <!-- Script -->
    <script>
        const form = document.getElementById('staffForm');
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
            let password = form.password.value.trim();

            // VALIDATION
            if (!name || !email || !password) {
                showModal(
                    "Validation Error",
                    "Please fill out all required staff details.",
                    [{
                        text: "OK",
                        class: "bg-[#55B85A] text-white px-4 py-2 rounded-lg hover:bg-[#4aa04c]",
                        action: closeModal
                    }]
                );
                return;
            }

            // CONFIRMATION
            showModal(
    "Confirm Save",
    "Are you sure you want to add this staff member?",
    [
        {
            text: "Cancel",
            class: "px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100",
            action: closeModal
        },
        {
            text: "Save",
            class: "px-4 py-2 rounded-lg bg-[#55B85A] text-white hover:bg-[#4aa04c]",
            action: () => {
                form.submit();
            }
        }
    ]
);
        });
    </script>

</x-app-layout>