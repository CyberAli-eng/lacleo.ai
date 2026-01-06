<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Welcome from '@/Components/Welcome.vue';
import Cookies from 'js-cookie';

// Environment variables
const apiHostUrl = import.meta.env.VITE_API_HOST_URL;
const webAppUrl = import.meta.env.VITE_WEB_APP_URL;

// Reactive variable to track loading state
const isLoading = ref(false); // Used to control when "Please wait..." is shown

// Function to get the bearer token from the cookie
const getBearerToken = () => {
    return Cookies.get(import.meta.env.VITE_ACCESS_TOKEN_NAME);
};

// Function to handle logout
const logout = () => {
    router.post(route('logout'));
};

// Function to check user authentication status
const checkUserStatus = async () => {
    try {
        const token = getBearerToken();
        if (!token) {
            logout();
            return;
        }

        const response = await axios.get(`${apiHostUrl}/api/v1/user`, {
            headers: {
                Authorization: `Bearer ${token}`,
            },
        });

        // If the response is successful, show "Please wait..." and redirect
        isLoading.value = true; // Set loading state to true to show the "Please wait..." message

        // Redirect after a short delay to give the user a chance to see the message
        window.location.href = webAppUrl;
    } catch (error) {
        if (error.response && error.response.status !== 200) {
            logout();
        } else {
            console.error('An error occurred:', error.message);
        }
    }
};

// Lifecycle hook to trigger the API call when the component is mounted
onMounted(() => {
    checkUserStatus();
});
</script>

<template>
    <div v-if="isLoading">
        Please wait...
    </div>
</template>
