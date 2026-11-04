/**
 * Универсальный экран авторизации и регистрации.
 *
 * Одна форма на два режима: вход и регистрация (email, пароль и подтверждение).
 * Вёрстка адаптивная: на телефоне карточка занимает экран и прокручивается при
 * открытой клавиатуре, на планшете и в web (ПК) — центрированная карточка
 * ограниченной ширины.
 */

import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ApiError } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';
import { FormAlert } from '../src/components/FormAlert';
import { PrimaryButton } from '../src/components/PrimaryButton';
import { SegmentedSwitch } from '../src/components/SegmentedSwitch';
import { TextField } from '../src/components/TextField';

type Mode = 'sign-in' | 'sign-up';

type FieldName = 'email' | 'password' | 'password_confirmation';

type FieldErrors = Partial<Record<FieldName, string>>;

const MODES = [
  { value: 'sign-in', label: 'Вход' },
  { value: 'sign-up', label: 'Регистрация' },
] as const;

const MIN_PASSWORD_LENGTH = 8;

/** Достать ошибки полей из ответа API (422). */
function extractFieldErrors(error: unknown): FieldErrors {
  if (!(error instanceof ApiError)) {
    return {};
  }

  const errors = error.payload.errors ?? {};
  const result: FieldErrors = {};

  (Object.keys(errors) as FieldName[]).forEach((field) => {
    const message = errors[field]?.[0];

    if (message) {
      result[field] = message;
    }
  });

  return result;
}

/** Текст общей ошибки для ответа API. */
function extractMessage(error: unknown): string {
  if (error instanceof Error) {
    return error.message;
  }

  return 'Не удалось выполнить запрос. Попробуйте ещё раз.';
}

export default function AuthScreen() {
  const { signIn, signUp } = useAuth();

  const [mode, setMode] = useState<Mode>('sign-in');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const isSignUp = mode === 'sign-up';

  const switchMode = (next: Mode) => {
    setMode(next);
    setFieldErrors({});
    setFormError(null);
    setPasswordConfirmation('');
  };

  /** Локальная проверка обязательных полей до запроса к API. */
  const validate = (): FieldErrors => {
    const errors: FieldErrors = {};

    if (email.trim() === '') {
      errors.email = 'Укажите email.';
    }

    if (password === '') {
      errors.password = 'Укажите пароль.';
    } else if (password.length < MIN_PASSWORD_LENGTH) {
      errors.password = `Пароль должен содержать минимум ${MIN_PASSWORD_LENGTH} символов.`;
    }

    if (isSignUp) {
      if (passwordConfirmation === '') {
        errors.password_confirmation = 'Повторите пароль.';
      } else if (passwordConfirmation !== password) {
        errors.password_confirmation = 'Пароли не совпадают.';
      }
    }

    return errors;
  };

  const submit = async () => {
    if (submitting) {
      return;
    }

    const localErrors = validate();

    if (Object.keys(localErrors).length > 0) {
      setFieldErrors(localErrors);
      setFormError(null);

      return;
    }

    setSubmitting(true);
    setFieldErrors({});
    setFormError(null);

    try {
      // Переход на главный экран выполняет гвард в app/_layout.tsx:
      // он следит за сессией и сам уводит с формы после входа или регистрации.
      if (isSignUp) {
        await signUp(email, password, passwordConfirmation);
      } else {
        await signIn(email, password);
      }
    } catch (error) {
      const apiFieldErrors = extractFieldErrors(error);

      setFieldErrors(apiFieldErrors);
      setFormError(Object.keys(apiFieldErrors).length > 0 ? null : extractMessage(error));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView className="flex-1 bg-surface">
      <KeyboardAvoidingView className="flex-1" behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView
          className="flex-1"
          contentContainerClassName="flex-grow justify-center px-4 py-8"
          keyboardShouldPersistTaps="handled"
          automaticallyAdjustKeyboardInsets
        >
          <View className="w-full max-w-md gap-6 self-center rounded-2xl border border-black/5 bg-card p-6">
            <View className="gap-2">
              <Text className="text-2xl font-bold text-app">JWP Board</Text>
              <Text className="text-sm text-muted">
                {isSignUp ? 'Создайте аккаунт по email и паролю.' : 'Войдите, чтобы продолжить работу.'}
              </Text>
            </View>

            <SegmentedSwitch options={MODES} value={mode} onChange={switchMode} />

            {formError ? <FormAlert message={formError} /> : null}

            <View className="gap-4">
              <TextField
                label="Email"
                value={email}
                onChangeText={setEmail}
                error={fieldErrors.email}
                placeholder="you@example.com"
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
                autoComplete="email"
                textContentType="emailAddress"
                editable={!submitting}
              />

              <TextField
                label="Пароль"
                value={password}
                onChangeText={setPassword}
                error={fieldErrors.password}
                placeholder="Минимум 8 символов"
                secureTextEntry
                autoCapitalize="none"
                autoComplete={isSignUp ? 'new-password' : 'current-password'}
                textContentType={isSignUp ? 'newPassword' : 'password'}
                editable={!submitting}
              />

              {isSignUp ? (
                <TextField
                  label="Повторите пароль"
                  value={passwordConfirmation}
                  onChangeText={setPasswordConfirmation}
                  error={fieldErrors.password_confirmation}
                  placeholder="Ещё раз"
                  secureTextEntry
                  autoCapitalize="none"
                  autoComplete="new-password"
                  textContentType="newPassword"
                  editable={!submitting}
                />
              ) : null}
            </View>

            <PrimaryButton
              label={isSignUp ? 'Зарегистрироваться' : 'Войти'}
              onPress={() => void submit()}
              loading={submitting}
            />

            <Pressable
              accessibilityRole="button"
              disabled={submitting}
              onPress={() => switchMode(isSignUp ? 'sign-in' : 'sign-up')}
              className="items-center py-1"
            >
              <Text className="text-sm text-muted">
                {isSignUp ? 'Уже есть аккаунт? ' : 'Нет аккаунта? '}
                <Text className="font-semibold text-app">{isSignUp ? 'Войти' : 'Зарегистрироваться'}</Text>
              </Text>
            </Pressable>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
