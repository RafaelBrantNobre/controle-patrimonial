export function classes(...valores: (string | undefined | false)[]) {
  return valores.filter(Boolean).join(' ')
}
