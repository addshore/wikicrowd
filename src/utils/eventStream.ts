export interface RecentChangeEvent {
  $schema: string;
  meta: {
    uri: string;
    request_id: string;
    id: string;
    dt: string;
    domain: string;
    stream: string;
    topic: string;
    partition: number;
    offset: number;
  };
  id: number; // ID of the recentchange event (rcid)
  type: string; // Type of recentchange event (rc_type)
  namespace: number; // ID of relevant namespace of affected page (rc_namespace, page_namespace)
  title: string; // Full page name, from Title::getPrefixedText
  title_url: string;
  comment: string; // (rc_comment)
  timestamp: number; // Unix timestamp (derived from rc_timestamp)
  user: string; // (rc_user_text)
  bot: boolean; // (rc_bot)
  notify_url: string;
  minor: boolean; // (rc_minor)
  patrolled: boolean; // (rc_patrolled)
  length: {
    old: number | null; // (rc_old_len)
    new: number | null; // (rc_new_len)
  };
  revision: {
    old: number | null; // (rc_this_oldid)
    new: number | null; // (rc_last_oldid)
  };
  server_url: string; // $wgCanonicalServer
  server_name: string; // $wgServerName
  server_script_path: string; // $wgScriptPath
  wiki: string; // wfWikiID ($wgDBprefix, $wgDBname)
  parsedcomment: string; // The rc_comment parsed into simple HTML. Optional
}

export function subscribeToRecentChanges() {
    const url = 'https://stream.wikimedia.org/v2/stream/recentchange';
    let eventSource: EventSource | null = null;
    let retryDelay = 1000; // Start with 1 second
    const maxRetryDelay = 10000; // Maximum backoff limit is 10 seconds

    const connect = () => {
        eventSource = new EventSource(url);

        eventSource.onopen = () => {
            // Reset retry delay on successful connection
            retryDelay = 1000;
        };

        eventSource.onerror = () => {
            if (eventSource) {
                eventSource.close();
            }
            // Exponential backoff with a cap
            setTimeout(connect, retryDelay);
            retryDelay = Math.min(retryDelay * 2, maxRetryDelay);
        };
    };

    connect();

    return {
        onMessage: (callback: (event: RecentChangeEvent) => void) => {
            if (eventSource) {
                eventSource.onmessage = (message) => {
                    const event: RecentChangeEvent = JSON.parse(message.data);
                    callback(event);
                };
            }
        },
        onError: (callback: (error: Event) => void) => {
            if (eventSource) {
                eventSource.onerror = (error) => {
                    callback(error);
                };
            }
        },
        close: () => {
            if (eventSource) {
                eventSource.close();
            }
        },
    };
}
