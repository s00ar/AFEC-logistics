export function positiveQuantity(value) {
  if (typeof value !== 'string' || !/^[1-9][0-9]{0,18}$/.test(value) || BigInt(value) > 9223372036854775807n) {
    throw new Error('Ingresá toneladas enteras positivas, sin separadores (máximo 9223372036854775807).');
  }
  return value;
}
