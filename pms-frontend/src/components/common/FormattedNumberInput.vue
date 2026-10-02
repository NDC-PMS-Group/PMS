<script setup lang="ts">
import { ref, watch } from 'vue';

const props = withDefaults(defineProps<{
  modelValue?: number | null;
  id?: string;
  placeholder?: string;
  decimals?: number;
  disabled?: boolean;
}>(), {
  modelValue: null,
  id: undefined,
  placeholder: '0.00',
  decimals: 2,
  disabled: false,
});

const emit = defineEmits<{
  'update:modelValue': [value: number | undefined];
}>();

const format = (value?: number | null) => {
  if (value === null || value === undefined || !Number.isFinite(Number(value))) return '';
  return new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 0,
    maximumFractionDigits: props.decimals,
  }).format(Number(value));
};

const displayValue = ref(format(props.modelValue));

watch(() => props.modelValue, (value) => {
  const numericDisplay = Number(displayValue.value.replace(/,/g, ''));
  if (value === undefined || value === null || Number(value) !== numericDisplay) {
    displayValue.value = format(value);
  }
});

const handleInput = (event: Event) => {
  const input = event.target as HTMLInputElement;
  const sanitized = input.value.replace(/[^\d.]/g, '');
  const [integerPart = '', ...decimalParts] = sanitized.split('.');
  const decimalPart = decimalParts.join('').slice(0, props.decimals);
  const normalizedInteger = integerPart.replace(/^0+(?=\d)/, '') || (sanitized ? '0' : '');
  const groupedInteger = normalizedInteger
    ? new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 }).format(Number(normalizedInteger))
    : '';
  displayValue.value = decimalParts.length
    ? `${groupedInteger}.${decimalPart}`
    : groupedInteger;
  input.value = displayValue.value;

  const numeric = Number(`${normalizedInteger || '0'}${decimalParts.length ? `.${decimalPart}` : ''}`);
  emit('update:modelValue', displayValue.value === '' || !Number.isFinite(numeric) ? undefined : numeric);
};

const handleBlur = () => {
  displayValue.value = format(props.modelValue);
};
</script>

<template>
  <input
    :id="id"
    class="formatted-number-input"
    :value="displayValue"
    type="text"
    inputmode="decimal"
    autocomplete="off"
    :disabled="disabled"
    :placeholder="placeholder"
    @input="handleInput"
    @blur="handleBlur"
  />
</template>

<style scoped>
.formatted-number-input {
  width: 100%;
  min-height: 2.7rem;
  padding: 0.6rem 0.7rem;
  border: 1px solid var(--border, #dbe3ee);
  border-radius: 0.4rem;
  background: var(--card, #ffffff);
  color: var(--text, #0f172a);
  caret-color: #2563eb;
  color-scheme: inherit;
  font: inherit;
  font-weight: 750;
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease,
    background-color 0.15s ease;
}

.formatted-number-input::placeholder {
  color: var(--muted, #94a3b8);
}

.formatted-number-input:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px color-mix(in srgb, #2563eb 18%, transparent);
}

.formatted-number-input:disabled {
  cursor: not-allowed;
  background: var(--soft, #f8fafc);
  color: var(--muted, #64748b);
  opacity: 1;
}
</style>
