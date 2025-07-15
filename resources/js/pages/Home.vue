<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { driver } from "driver.js"
import "driver.js/dist/driver.css";


// const driverObj = driver();

// driverObj.highlight({
//     element: "#some-element",
//     popover: {
//         title: "¡Hola!",
//         description: "Esta es una breve introducción a PAI-Bot."
//     }
// })

//Obtener el año actual en formato yyyy
const currentYear = new Date().getFullYear();

</script>
<template>
    <Head title="PAI-Agente"></Head>

    <div class="dark:bg-gray-900 bg-gray-100 text-gray-800 dark:text-gray-200 transition-all-smooth">

        <div class="container mx-auto px-6 py-8">
            <header class="flex justify-between items-center mb-12">
                <div class="flex items-center space-x-2">

                    <svg class="h-10 w-10 text-green-500 dark:text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" fill="currentColor">
                        <path d="M 25 2 C 12.3 2 2 12.3 2 25 C 2 29.1 3.1 32.899219 5 36.199219 L 2 46.699219 C 1.9 46.999219 1.9992187 47.399219 2.1992188 47.699219 C 2.4992187 47.999219 2.8992187 48 3.1992188 48 L 14.199219 45.300781 C 17.399219 47.000781 21.1 48 25 48 C 37.7 48 48 37.7 48 25 C 48 12.3 37.7 2 25 2 z M 25 4 C 36.6 4 46 13.4 46 25 C 46 36.6 36.6 46 25 46 C 21.3 46 17.800781 45.000781 14.800781 43.300781 C 14.600781 43.200781 14.299609 43.099219 14.099609 43.199219 L 4.5 45.599609 L 7 36.400391 C 7.1 36.100391 7.0003906 35.899609 6.9003906 35.599609 C 5.1003906 32.499609 4 28.9 4 25 C 4 13.4 13.4 4 25 4 z M 18.113281 12.988281 C 17.925781 12.975781 17.800781 13 17.800781 13 L 16.599609 13 C 15.999609 13 15.100781 13.2 14.300781 14 C 13.800781 14.5 12 16.3 12 19.5 C 12 22.9 14.299609 25.799609 14.599609 26.099609 C 14.599609 26.099609 15 26.600781 15.5 27.300781 C 16 28.000781 16.699609 28.800781 17.599609 29.800781 C 19.399609 31.700781 21.899609 33.899219 25.099609 35.199219 C 26.499609 35.799219 27.699609 36.2 28.599609 36.5 C 30.199609 37 31.700781 36.900781 32.800781 36.800781 C 33.600781 36.700781 34.500391 36.299219 35.400391 35.699219 C 36.300391 35.099219 37.199609 34.400391 37.599609 33.400391 C 37.899609 32.600391 37.999609 31.900781 38.099609 31.300781 L 38.099609 30.5 C 38.099609 30.2 38.000781 30.200781 37.800781 29.800781 C 37.300781 29.000781 36.799219 29.000781 36.199219 28.800781 C 35.899219 28.600781 34.999219 28.200781 34.199219 27.800781 C 33.299219 27.400781 32.599609 27.000781 32.099609 26.800781 C 31.799609 26.700781 31.400391 26.499609 30.900391 26.599609 C 30.400391 26.699609 29.899609 27 29.599609 27.5 C 29.299609 27.9 28.200781 29.299219 27.800781 29.699219 L 27.699219 29.599609 C 27.299219 29.399609 26.7 29.200781 26 28.800781 C 25.2 28.400781 24.299219 27.800781 23.199219 26.800781 C 21.599219 25.400781 20.499219 23.699609 20.199219 23.099609 C 20.499219 22.699609 20.899609 22.3 21.099609 22 C 21.199609 21.9 21.280859 21.799219 21.349609 21.699219 C 21.418359 21.599219 21.475391 21.500391 21.525391 21.400391 C 21.625391 21.200391 21.700781 21.000781 21.800781 20.800781 C 22.200781 20.100781 22.000781 19.300391 21.800781 18.900391 C 21.800781 18.900391 21.7 18.600781 21.5 18.300781 C 21.4 18.000781 21.2 17.499609 21 17.099609 C 20.6 16.199609 20.2 15.199609 20 14.599609 C 19.7 13.899609 19.300781 13.399219 18.800781 13.199219 C 18.550781 13.049219 18.300781 13.000781 18.113281 12.988281 z M 16.599609 15 L 17.699219 15 L 17.900391 15 C 17.900391 15 17.999609 15.100391 18.099609 15.400391 C 18.299609 16.000391 18.799609 17.000391 19.099609 17.900391 C 19.299609 18.300391 19.499609 18.799609 19.599609 19.099609 C 19.699609 19.399609 19.800391 19.600781 19.900391 19.800781 C 19.900391 19.900781 20 19.900391 20 19.900391 C 19.8 20.300391 19.8 20.399219 19.5 20.699219 C 19.2 21.099219 18.799219 21.499219 18.699219 21.699219 C 18.599219 21.899219 18.299609 22.1 18.099609 22.5 C 17.899609 22.9 18.000781 23.599609 18.300781 24.099609 C 18.700781 24.699609 19.900781 26.700391 21.800781 28.400391 C 23.000781 29.500391 24.1 30.199609 25 30.599609 C 25.9 31.099609 26.600781 31.300391 26.800781 31.400391 C 27.200781 31.600391 27.599609 31.699219 28.099609 31.699219 C 28.599609 31.699219 29.000781 31.3 29.300781 31 C 29.700781 30.6 30.699219 29.399609 31.199219 28.599609 L 31.400391 28.699219 C 31.400391 28.699219 31.699609 28.8 32.099609 29 C 32.499609 29.2 32.900391 29.399609 33.400391 29.599609 C 34.300391 29.999609 35.100391 30.399609 35.400391 30.599609 L 36 30.900391 L 36 31.199219 C 36 31.599219 35.899219 32.200781 35.699219 32.800781 C 35.599219 33.100781 35.000391 33.699609 34.400391 34.099609 C 33.700391 34.499609 32.899609 34.800391 32.599609 34.900391 C 31.699609 35.000391 30.600781 35.099219 29.300781 34.699219 C 28.500781 34.399219 27.4 34.1 26 33.5 C 23.2 32.3 20.899219 30.3 19.199219 28.5 C 18.399219 27.6 17.699219 26.799219 17.199219 26.199219 C 16.699219 25.599219 16.500781 25.2 16.300781 25 C 15.900781 24.6 14 21.999609 14 19.599609 C 14 17.099609 15.200781 16.100391 15.800781 15.400391 C 16.100781 15.000391 16.499609 15 16.599609 15 z"></path>
                    </svg>
                    <span class="font-semibold text-xl ">PAI - Agente Virtual</span>
                </div>
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="hidden md:inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-md leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="hidden md:inline-block rounded-sm border border-transparent px-5 p-2 mt-4 text-md leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                    >
                        Admin
                    </Link>

                </template>
            </header>

            <section class="text-center mb-24">
                <div class="flex flex-col md:flex-row itemx-center justify-center gap-8">
                    <div class="md:w-1/2 text-left">
                        <h1 class="text-4xl md:text-6xl font-extrabold mb-4 leading-tight">
                            Tu asistente virtual <span class="text-green-600">en WhatsApp</span>
                        </h1>
                        <p class="text-lg md:text-xl text-gray-700 dark:text-gray-200 mb-8">
                        Automatiza la consulta de archivos y gestiona solicitudes 24/7.
                        </p>
                        <a href="https://wa.me/message/NPWBZ3KOUMVUF1" class="bg-green-500 hover:bg-white text-white hover:text-green-600 font-bold py-3 px-8 rounded-full text-lg transition-all transition-all-smooth shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                            Clic aquí!
                        </a>
                    </div>
                    <div class="md:w-1/2">
                    <img src="images/bot.png"
                         alt="PAI Agente"
                         class="rounded-lg w-full max-w-md mx-auto">
                    </div>
                </div>
            </section>

            <section class="mb-24">
                <h2 class="text-3xl md:text-4xl font-bold text-center mb-12">¿Cómo funciona?</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Paso 1 -->
                <div id="step-init" class="glass-card p-6 rounded-2xl text-center transition-all-smooth hover:scale-105">
                    <img src="https://placehold.co/200x200/D1FAE5/10B981?text=Paso+1" alt="Ilustración de inicio de conversación" class="w-24 h-24 mx-auto mb-4 rounded-full border-4 border-white dark:border-gray-800">
                    <h3 class="font-bold text-xl mb-2">Inicia la conversación</h3>
                    <p class="text-gray-600 dark:text-gray-400">Envía un "Hola" a nuestro número de WhatsApp para empezar.</p>
                </div>
                <!-- Paso 2 -->
                <div id="step-validate" class="glass-card p-6 rounded-2xl text-center transition-all-smooth hover:scale-105">
                    <img src="https://placehold.co/200x200/DBEAFE/3B82F6?text=Paso+2" alt="Ilustración de validación de cliente" class="w-24 h-24 mx-auto mb-4 rounded-full border-4 border-white dark:border-gray-800">
                    <h3 class="font-bold text-xl mb-2">Validación de cliente</h3>
                    <p class="text-gray-600 dark:text-gray-400">Selecciona si eres cliente y valida con tu RFC</p>
                </div>
                <!-- Paso 3 -->
                <div id="step-options" class="glass-card p-6 rounded-2xl text-center transition-all-smooth hover:scale-105">
                    <img src="https://placehold.co/200x200/FEF3C7/F59E0B?text=Paso+3" alt="Ilustración de menú de opciones" class="w-24 h-24 mx-auto mb-4 rounded-full border-4 border-white dark:border-gray-800">
                    <h3 class="font-bold text-xl mb-2">Elige una opción</h3>
                    <p class="text-gray-600 dark:text-gray-400">Selecciona si quieres descargar archivos o hablar con un agente.</p>
                </div>
                <!-- Paso 4 -->
                <div id="step-done" class="glass-card p-6 rounded-2xl text-center transition-all-smooth hover:scale-105">
                    <img src="https://placehold.co/200x200/FCE7F3/EC4899?text=Paso+4" alt="Ilustración de tarea completada" class="w-24 h-24 mx-auto mb-4 rounded-full border-4 border-white dark:border-gray-800">
                    <h3 class="font-bold text-xl mb-2">¡Listo!</h3>
                    <p class="text-gray-600 dark:text-gray-400">Recibes tus archivos al instante o un agente es notificado.</p>
                </div>
            </div>
            </section>

            <section class="mb-24">
             <h2 class="text-3xl md:text-4xl font-bold text-center mb-12">PAI en acción</h2>
             <div class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden dark:shadow-white shadow-gray-700">
                <div class="bg-gray-200 dark:bg-gray-900 p-3 flex items-center space-x-3">
                    <img src="images/bot.png" class="rounded-full h-8 w-8" alt="Avatar">
                    <div>
                        <p class="font-semibold">Agente - PAI</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">en línea</p>
                    </div>
                </div>
                <div class="p-4 h-80 overflow-y-auto" style="background-image: url('https://i.pinimg.com/736x/8c/98/99/8c98994518b575bfd8c949e91d20548b.jpg');">
                    <!-- Chat messages -->
                    <div class="flex justify-end mb-3">
                        <div class="bg-green-200 dark:bg-green-800 text-black dark:text-white rounded-lg py-2 px-3 max-w-xs">Hola</div>
                    </div>
                     <div class="flex justify-start mb-3">
                        <div class="bg-white dark:bg-gray-700 rounded-lg py-2 px-3 max-w-xs">
                            ¡Hola! Soy tu asistente virtual. Para continuar, por favor, indícame tu ID de cliente.
                        </div>
                    </div>
                    <div class="flex justify-end mb-3">
                        <div class="bg-green-200 dark:bg-green-800 text-black dark:text-white rounded-lg py-2 px-3 max-w-xs">XAXX010101000</div>
                    </div>
                     <div class="flex justify-start mb-3">
                        <div class="bg-white dark:bg-gray-700 rounded-lg py-2 px-3 max-w-xs">
                           ¡Gracias! Cliente verificado. ¿Qué deseas hacer? <br>
                           1. Descargar Facturas <br>
                           2. Hablar con un agente
                        </div>
                    </div>
                    <div class="flex justify-end mb-3">
                        <div class="bg-green-200 dark:bg-green-800 text-black dark:text-white rounded-lg py-2 px-3 max-w-xs">Descargar Archivos</div>
                    </div>
                     <div class="flex justify-start mb-3">
                        <div class="bg-white dark:bg-gray-700 rounded-lg py-2 px-3 max-w-xs">
                           Se han enviado los archivos solicitados. <br>
                           Hasta Pronto!
                        </div>
                    </div>
                </div>
             </div>
            </section>

            <!-- <section class="text-center bg-primary-DEFAULT dark:bg-primary-dark text-white p-12 rounded-2xl shadow-xl">
                <h2 class="text-3xl font-bold mb-4">¿Listo para automatizar tu negocio?</h2>
                <p class="mb-8 max-w-2xl mx-auto">Implementa tu propio asistente en minutos y mejora la experiencia de tus clientes.</p>
                <a href="https://wa.me/1234567890?text=Hola,%20quiero%20mi%20asistente%20virtual" target="_blank" class="bg-whatsapp hover:bg-whatsapp-dark text-white font-bold py-3 px-8 rounded-full text-lg transition-all-smooth shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                    Quiero mi asistente ahora
                </a>
            </section> -->

            <footer class="text-center mt-16 text-gray-500 dark:text-gray-400">
                <p>&copy; {{ currentYear }}  Franco Padilla Corporativo. Todos los derechos reservados.</p>
                <div class="flex justify-center space-x-4 mt-4">
                    <a href="https://fpcorporativo.com/news-details/12" class="hover:text-indigo-900">Política de Privacidad</a>
                    <!-- <a href="#" class="hover:text-primary-DEFAULT dark:hover:text-primary-dark">Soporte</a>
                    <a href="#" class="hover:text-primary-DEFAULT dark:hover:text-primary-dark">Contacto</a> -->
                </div>
            </footer>
        </div>
    </div>
</template>

<style scoped>

.transition-all-smooth {
            transition: all 0.3s ease-in-out;
        }
        /* Estilo de Glassmorphism */
        .glass-card {
            background: rgba(55, 151, 103, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .glass-card:hover {
            background: rgba(100, 161, 129, 0.3);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
</style>