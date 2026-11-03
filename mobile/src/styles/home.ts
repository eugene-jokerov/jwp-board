import { StyleSheet } from 'react-native';

// Styles for the starter stub screen.
export const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    gap: 12,
    backgroundColor: '#f5f6f8',
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
  },
  subtitle: {
    fontSize: 14,
    color: '#6b7280',
  },
  card: {
    width: '100%',
    gap: 8,
    padding: 16,
    borderRadius: 12,
    backgroundColor: '#ffffff',
  },
  label: {
    fontSize: 12,
    color: '#9ca3af',
  },
  value: {
    fontSize: 14,
    color: '#111827',
  },
  ok: {
    marginTop: 8,
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 8,
    overflow: 'hidden',
    fontSize: 14,
    backgroundColor: '#dcfce7',
    color: '#166534',
  },
  fail: {
    marginTop: 8,
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 8,
    overflow: 'hidden',
    fontSize: 14,
    backgroundColor: '#fee2e2',
    color: '#991b1b',
  },
  spinner: {
    marginTop: 12,
  },
  button: {
    width: '100%',
    alignItems: 'center',
    paddingVertical: 12,
    borderRadius: 10,
    backgroundColor: '#111827',
  },
  buttonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '600',
  },
});
