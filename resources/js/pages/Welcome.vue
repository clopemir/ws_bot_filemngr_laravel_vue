<script setup lang="ts">
    import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <!-- <div class="font-comic min-h-screen bg-gradient-to-br from-yellow-100 via-white to-green-100 text-gray-900"> -->
    <div class="min-h-screen flex flex-col bg-gradient-to-br from-yellow-100 via-white to-green-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 font-comic text-gray-900 dark:text-white">


        <Head title="Welcome">

        </Head>
        <header class="not-has-[nav]:hidden mt-4 mb-4 py-2 w-full">
            <nav class="flex justify-center gap-4">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-md leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="inline-block rounded-sm border border-transparent px-5 p-2 mt-4 text-md leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                    >
                        Admin
                    </Link>

                </template>
            </nav>
        </header>

        <!-- Hero -->
        <main class="flex-grow mt-8">

            <section class="max-w-7xl mx-auto flex flex-col items-center px-6 py-8 text-center md:px-12 xl:px-24">
            <img width="64" height="64" src="https://img.icons8.com/nolan/64/bot.png" alt="bot"/>
            <h1 class="comic-shadow dark:comic-shadow-dark mb-4 mt-4 text-4xl font-bold md:text-5xl border-2 p-4 rounded-lg border-black dark:border-white leading-tight">¡Prueba PAI-Bot en WhatsApp!</h1>
            <p class="mb-8 max-w-xl text-lg md:text-xl">Consulta tus opiniones, constancias e impuestos desde tu teléfono.</p>
            <a
                href="https://wa.me/message/NPWBZ3KOUMVUF1"
                target="_blank"
                rel="noopener noreferrer"
                class="bg-green-500 text-white py-3 px-6 text-xl rounded-lg border-4 border-black transform transition-all hover:scale-105 comic-btn dark:bg-green-600 dark:border-white"
            >
                🚀 Pruébalo Ahora
            </a>
        </section>

        <!-- Features -->
        <section class="bg-white dark:bg-gray-800 py-12 px-6 md:px-16 mt-8">
            <div class="grid gap-8 text-center md:grid-cols-2">
                <div class="p-4 border-2 border-black dark:border-white rounded-lg comic-shadow dark:comic-shadow-dark transition-all hover:scale-105">
                    <h2 class="mb-2 text-2xl font-bold">🤖 Atención 24/7</h2>
                    <p>Responde a solicitudes en cualquier momento.</p>
                </div>
                <div class="p-4 border-2 border-black dark:border-white rounded-lg comic-shadow dark:comic-shadow-dark transition-all hover:scale-105">
                    <h2 class="mb-2 text-2xl font-bold">📦 Envía archivos</h2>
                    <p>Consulta tus opiniones de cumplimiento, constancias e impuestos de forma automática.</p>
                </div>
            </div>
        </section>

        </main>

        <!-- Footer -->
        <footer class="text-center py-6 bg-yellow-100 dark:bg-gray-900 mt-10 border-t border-black dark:border-white">
            <p class="text-sm">© 2025 PAI-Bot 🚀</p>
        </footer>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Comic+Neue:wght@700&display=swap');

.font-comic {
  font-family: 'Comic Neue', cursive;
}

.comic-shadow {
  box-shadow: 4px 4px 0px #000000;
}

.comic-shadow-dark {
  box-shadow: 4px 4px 0px #ffffff;
}

.comic-btn {
  box-shadow: 3px 3px 0px #000000;
  border-radius: 8px;
  font-family: 'Comic Neue', cursive;
}
</style>
