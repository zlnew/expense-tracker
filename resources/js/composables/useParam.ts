export function useParam() {
  return new URLSearchParams(
    typeof window !== 'undefined' ? window.location.search : '',
  )
}
