<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Payment Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <script src="//unpkg.com/alpinejs" defer></script>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body>
    <div class="min-h-screen flex items-center justify-center p-4" x-data="payments">
        <div class="shadow-[rgba(0,_0,_0,_0.25)_0px_25px_50px_-12px] rounded-lg py-6 px-10 w-full lg:w-2/5">
            <div x-cloak x-show="loading">
                <img src="./processing.gif" class="w-full h-auto" alt="Processing" />
            </div>

            <div x-cloack x-show="!loading">
                <div class="text-center mb-10">
                    <h6 class="text-3xl text-green-500 mb-6" x-text="title"></h6>
                    <div>
                        <template x-if="completed">
                            <iconify-icon icon="teenyicons:tick-circle-outline"
                                class="text-5xl text-green-500"></iconify-icon>
                        </template>
                        <template x-if="!completed">
                            <iconify-icon icon="material-symbols:cancel-outline"
                                class="text-5xl text-green-500"></iconify-icon>
                        </template>
                    </div>
                </div>
                <table class="w-full table-auto mb-10">
                    <tbody>
                        <tr>
                            <td class="text-gray-600 text-start pb-2">Transaction ID</td>
                            <td class="text-gray-900 text-end pb-2" x-text="transactionId"></td>
                        </tr>
                        <tr>
                            <td class="text-gray-600 text-start pb-2">Payment Type</td>
                            <td class="text-gray-900 text-end pb-2 capitalize" x-text="gateway"></td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-center gap-4">
                    <a href="/" class="px-6 py-2 rounded-md bg-blue-500 uppercase text-white">Go Home</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('payments', () => ({
                transactionId: null,
                status: null,
                gateway: null,
                title: '',
                loading: true,
                completed: false,

                async init() {
                    let url = new URLSearchParams(window.location.search)
                    this.status = url.get('status')
                    this.gateway = url.get('gateway')
                    this.transactionId = url.get('transaction_id')

                    await fetch(
                            `${window.origin}/api/v1/online-payment-check/${this.transactionId}`, {
                                method: 'GET',
                                headers: {
                                    Accept: 'application/json'
                                }
                            })
                        .then(res => res.json())
                        .then(res => {
                            this.loading = false;

                            if (res.status !== 'success') {
                                this.title = 'Payment Failed';
                                this.completed = false;
                                return;
                            }

                            this.title = 'Payment Successful';
                            this.completed = true;
                        })
                        .catch(err => console.log(err))
                }
            }))
        })
    </script>
</body>

</html>
