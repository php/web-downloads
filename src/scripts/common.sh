function fetch_artifact() {
  local filepath=$1
  local url=$2
  local token=$3
  if [[ "$url" == *api.github.com* ]]; then
    curl -H "Accept: application/vnd.github+json" -H "Authorization: token $token" -L -o "$filepath" "$url"
  else
    curl -L -o "$filepath" "$url"
  fi
}