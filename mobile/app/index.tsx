import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, Text, View } from 'react-native';

import { fetchHealth } from '../src/api/client';
import type { HealthPayload } from '../src/api/types';
import { API_URL } from '../src/config/env';
import { styles } from '../src/styles/home';

// Starter screen stub: checks connectivity with the backend API.
export default function HomeScreen() {
  const [health, setHealth] = useState<HealthPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const check = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      setHealth(await fetchHealth());
    } catch (e) {
      setHealth(null);
      setError(e instanceof Error ? e.message : 'unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void check();
  }, [check]);

  return (
    <View style={styles.container}>
      <Text style={styles.title}>JWP Board</Text>
      <Text style={styles.subtitle}>Mobile client (stub)</Text>

      <View style={styles.card}>
        <Text style={styles.label}>API</Text>
        <Text style={styles.value}>{API_URL}</Text>

        {loading ? <ActivityIndicator style={styles.spinner} /> : null}

        {!loading && health ? (
          <Text style={styles.ok}>Status: {health.status}</Text>
        ) : null}

        {!loading && !health ? (
          <Text style={styles.fail}>No connection: {error}</Text>
        ) : null}
      </View>

      <Pressable style={styles.button} onPress={() => void check()}>
        <Text style={styles.buttonText}>Check again</Text>
      </Pressable>
    </View>
  );
}
