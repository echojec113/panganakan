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
        #staff-password::-ms-reveal {
            display: none;
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

                   @include('staff._profile-fields')

                   <div>
                       <label for="staff-password" class="staff-label block text-sm font-medium">Password <span class="text-red-500">*</span></label>
                       <div class="relative mt-1">
                           <input id="staff-password" type="password" name="password" required minlength="8" autocomplete="new-password" class="staff-input block h-10 w-full rounded-lg border border-gray-300 py-2 pl-3 pr-10 text-sm text-gray-700 transition focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A]">
                           <button
                               type="button"
                               data-password-toggle="staff-password"
                               aria-label="Show password"
                               aria-pressed="false"
                               class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-gray-400 transition hover:text-[#367E4B] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#55B85A]"
                           >
                               <svg data-icon="show" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                   <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                                   <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                               </svg>
                               <svg data-icon="hide" class="hidden h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                   <path d="M9.88 9.88a3 3 0 1 0 4.243 4.243" />
                                   <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.478 0 8.268 2.943 9.543 7a9.96 9.96 0 0 1-1.563 3.029" />
                                   <path d="M6.603 6.602A10.08 10.08 0 0 0 2.458 12c1.274 4.057 5.064 7 9.542 7a9.94 9.94 0 0 0 5.399-1.603" />
                                   <line x1="3" y1="3" x2="21" y2="21" />
                               </svg>
                           </button>
                       </div>
                   </div>

                   <x-input-error :messages="$errors->get('password')" class="mt-2" />
                   <p class="text-sm text-gray-500">Use at least 8 characters, following the same password policy as password recovery.</p>
                   <div>
                       <label for="password_confirmation" class="staff-label block text-sm font-medium">Confirm Password <span class="text-red-500">*</span></label>
                       <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="staff-input mt-1 block w-full min-w-0 rounded-lg border-gray-300 text-sm">
                       <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
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
    <div id="popupModal" class="fixed inset-0 backdrop-blur-md bg-black/10 flex items-center justify-center hidden z-50" style="padding: 16px; overflow-y: auto;">
        <div class="staff-modal w-80 rounded-2xl bg-white p-6 text-center shadow-xl" style="max-width: 100%; max-height: calc(100dvh - 32px); overflow-y: auto; overflow-wrap: anywhere;">
            <h2 id="modalTitle" class="staff-modal-title mb-2 text-lg font-bold"></h2>
            <p id="modalMessage" class="staff-modal-message mb-4 text-sm"></p>

            <div id="modalButtons" class="flex justify-center gap-2"></div>
        </div>
    </div>

    <!-- Script -->
    <script>
        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.getElementById(button.getAttribute('data-password-toggle'));
                if (!input) {
                    return;
                }

                const willShow = input.type === 'password';
                input.type = willShow ? 'text' : 'password';
                button.setAttribute('aria-pressed', willShow ? 'true' : 'false');
                button.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
                button.querySelector('[data-icon="show"]')?.classList.toggle('hidden', willShow);
                button.querySelector('[data-icon="hide"]')?.classList.toggle('hidden', !willShow);
            });
        });

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

            let firstName = form.first_name.value.trim();
            let lastName = form.last_name.value.trim();
            let email = form.email.value.trim();
            let password = form.password.value.trim();

            // VALIDATION
            if (!firstName || !lastName || !email || !password) {
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
