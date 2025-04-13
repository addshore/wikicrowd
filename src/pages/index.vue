<template>
  <NavBar />
  <v-container>
    <v-row justify="center" align="center" style="height: 100vh;" v-if="!user">
      <h3>Welcome to WikiCrowd</h3>
    </v-row>
    <div v-if="user">
      <h3>Welcome, {{ user.username }}</h3>
      <p>This tool provides a wrapper around editing various parts of Wikimedia projects.</p>
      <p>It evolves from "wikicrowd" which was quick and easy micro contributions to the wiki space, showing what images depict.</p>
      <p>Using this tool will result in edits being made for your account.</p>
      <div v-if="accessToken">
        <h4>Edit Stats</h4>
        <div v-if="wdEditCount">
          <p>You have made {{ wdEditCount }} Wikidata edits! 🎉</p>
        </div>
        <div v-if="cmEditCount">
          <p>You have made {{ cmEditCount }} Commons edits! 🎉</p>
        </div>
        <div v-else>
          <p>Loading...</p>
        </div>
      </div>
    </div>
  </v-container>
</template>

<script lang="ts" setup>
import { ref } from 'vue';
import { getAccessToken, getUser } from '../utils/storage';
import NavBar from '../components/NavBar.vue';

const user = ref(getUser());

const accessToken = ref(getAccessToken());
const wdEditCount = ref(null);
const cmEditCount = ref(null);
if (accessToken.value) {
  fetch('https://www.wikidata.org/w/api.php?action=query&meta=userinfo&uiprop=editcount&format=json&formatversion=2&crossorigin=', {
    headers: {
      Authorization: `Bearer ${accessToken.value}`,
    },
    method: 'POST',
  }).then(r => r.json()).then(r => {
    const { userinfo } = r.query;
    if (userinfo.name !== user.value.username) {
      console.warn(`Inconsistent user name! OAuth "${user.value.username}" != MediaWiki "${userinfo.name}"`, r);
      return;
    }
    wdEditCount.value = r.query.userinfo.editcount;
  }).catch(e => {
    console.error('API request failed :( try logging out and back in again?', e);
  });
  // Also for commons...
  fetch('https://commons.wikimedia.org/w/api.php?action=query&meta=userinfo&uiprop=editcount&format=json&formatversion=2&crossorigin=', {
    headers: {
      Authorization: `Bearer ${accessToken.value}`,
    },
    method: 'POST',
  }).then(r => r.json()).then(r => {
    const { userinfo } = r.query;
    if (userinfo.name !== user.value.username) {
      console.warn(`Inconsistent user name! OAuth "${user.value.username}" != MediaWiki "${userinfo.name}"`, r);
      return;
    }
    cmEditCount.value = r.query.userinfo.editcount;
  }).catch(e => {
    console.error('API request failed :( try logging out and back in again?', e);
  });
}

</script>
