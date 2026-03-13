const SITE_BASE_URLS = {
  commons: 'https://commons.wikimedia.org',
  wikidata: 'https://www.wikidata.org',
};

function hasApiToken() {
  return typeof window !== 'undefined' && !!window.apiToken;
}

function cleanParams(params = {}) {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => value !== undefined && value !== null)
  );
}

function withDefaultFormat(params = {}) {
  const normalized = cleanParams(params);
  if (!normalized.format) {
    normalized.format = 'json';
  }
  return normalized;
}

function buildDirectRequest(site, params = {}) {
  const baseUrl = SITE_BASE_URLS[site];
  if (!baseUrl) {
    throw new Error(`Unsupported Wikimedia site: ${site}`);
  }

  const directParams = new URLSearchParams({
    ...withDefaultFormat(params),
    origin: '*',
  });

  return {
    url: `${baseUrl}/w/api.php?${directParams.toString()}`,
    options: {
      headers: {
        Accept: 'application/json',
      },
    },
  };
}

export function buildWikimediaActionRequest(site, params = {}) {
  const queryParams = withDefaultFormat(params);

  // Prefer backend proxy for browser calls so authenticated sessions can be used.
  // Fallback to direct Wikimedia requests is handled in fetchWikimediaActionApi.
  if (typeof window !== 'undefined') {
    const proxyParams = new URLSearchParams({
      site,
      ...queryParams,
    });

    return {
      url: `/api/wikimedia/action?${proxyParams.toString()}`,
      options: {
        headers: {
          Accept: 'application/json',
          ...(hasApiToken() ? { Authorization: `Bearer ${window.apiToken}` } : {}),
        },
      },
    };
  }

  return buildDirectRequest(site, queryParams);
}

export async function fetchWikimediaActionApi(site, params = {}, options = {}) {
  const request = buildWikimediaActionRequest(site, params);
  return fetch(request.url, {
    redirect: 'follow',
    ...request.options,
    ...options,
    headers: {
      ...(request.options?.headers || {}),
      ...(options.headers || {}),
    },
  });
}

export function buildWikimediaRestRequest(site, params = {}) {
  if (typeof window === 'undefined') {
    throw new Error('Wikimedia REST proxy requests are only supported in browser context.');
  }

  const queryParams = cleanParams(params);
  const restParams = new URLSearchParams({
    site,
    ...queryParams,
  });

  return {
    url: `/api/wikimedia/rest?${restParams.toString()}`,
    options: {
      headers: {
        Accept: 'application/json',
        ...(hasApiToken() ? { Authorization: `Bearer ${window.apiToken}` } : {}),
      },
    },
  };
}

export async function fetchWikimediaRestApi(site, params = {}, options = {}) {
  const request = buildWikimediaRestRequest(site, params);
  return fetch(request.url, {
    redirect: 'follow',
    ...request.options,
    ...options,
    headers: {
      ...(request.options?.headers || {}),
      ...(options.headers || {}),
    },
  });
}
