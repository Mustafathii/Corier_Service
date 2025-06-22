<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <span class="text-green-600 text-xl">📊</span>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                        <p class="text-2xl font-bold text-green-600">
                            E£ {{ number_format($totalRevenue) }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <span class="text-blue-600 text-xl">💰</span>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600">Payments Received</p>
                        <p class="text-2xl font-bold text-blue-600">
                            E£ {{ number_format($paymentsReceived) }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                            <span class="text-red-600 text-xl">📋</span>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600">Outstanding</p>
                        <p class="text-2xl font-bold text-red-600">
                            E£ {{ number_format($outstanding) }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <span class="text-purple-600 text-xl">🚗</span>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600">Driver Commissions</p>
                        <p class="text-2xl font-bold text-purple-600">
                            E£ {{ number_format($commissions) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
            <h2 class="text-xl font-bold mb-4 text-gray-900">📈 Financial Overview</h2>
            <div class="text-gray-600">
                <p>💡 <strong>Quick Summary:</strong></p>
                <ul class="mt-2 space-y-1">
                    <li>• Total business revenue: <strong>E£ {{ number_format($totalRevenue) }}</strong></li>
                    <li>• Money collected: <strong>E£ {{ number_format($paymentsReceived) }}</strong></li>
                    <li>• Still owed: <strong>E£ {{ number_format($outstanding) }}</strong></li>
                    <li>• Driver payments: <strong>E£ {{ number_format($commissions) }}</strong></li>
                </ul>
            </div>
        </div>
    </div>
</x-filament-panels::page>
