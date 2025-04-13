<template>
  <v-container>
    <h3 v-if="user">Redirecting...</h3>
    <p v-else-if="error">{{ error }}</p>
    <p v-else>Loading...</p>
  </v-container>
</template>

<script>
import { setUser, setAccessToken } from '../utils/storage';
import { useRouter } from 'vue-router';
import { fetchToken, fetchUserProfile } from '../utils/oauth';

export default {
  data() {
    return {
      user: null,
      error: null,
    };
  },
  async mounted() {
    const router = useRouter();
    const params = new URLSearchParams(window.location.search);
    const code = params.get('code');
    const codeVerifier = localStorage.getItem('code_verifier');

    if (code && codeVerifier) {
      try {
        const tokenData = await fetchToken(code, codeVerifier);
        setAccessToken(tokenData.access_token);

        const userProfile = await fetchUserProfile(tokenData.access_token);
        this.user = userProfile;
        setUser(userProfile);

        router.push('/');
      } catch (error) {
        this.error = error.message;
      }
    } else {
      this.error = 'Code or code verifier is missing';
    }
  },
};
</script>
