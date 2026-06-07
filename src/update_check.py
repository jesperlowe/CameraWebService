import logging

import httpx

logger = logging.getLogger("camera_uploader")

_RELEASES_URL = "https://api.github.com/repos/{repo}/releases/latest"


def _parse_version(value: str) -> tuple[int, ...] | None:
    """Parse 'v1.2.3' / '1.2.3' into a comparable tuple of ints, or None if not semver-like."""
    parts = value.strip().lstrip("vV").split(".")
    try:
        return tuple(int(p) for p in parts)
    except ValueError:
        return None


def check_for_update(current_version: str, repo: str) -> dict:
    """Check the GitHub "latest release" for `repo` against `current_version`.

    Returns {"available": bool, "latest": str|None, "url": str|None}.
    Never raises — network/parse errors simply yield "no update available"
    so a flaky connection can't surface a misleading banner.
    """
    try:
        resp = httpx.get(
            _RELEASES_URL.format(repo=repo),
            timeout=10,
            headers={"Accept": "application/vnd.github+json"},
        )
        if resp.status_code == 404:
            # Repo has no published releases yet — nothing to compare against.
            return {"available": False, "latest": None, "url": None}
        resp.raise_for_status()
        data = resp.json()

        tag     = (data.get("tag_name") or "").strip()
        latest  = _parse_version(tag)
        current = _parse_version(current_version)
        available = bool(latest and current and latest > current)

        return {
            "available": available,
            "latest":    tag.lstrip("vV") or None,
            "url":       data.get("html_url") or f"https://github.com/{repo}/releases",
        }
    except Exception as exc:
        logger.debug("Versionstjek mod GitHub fejlede: %s", exc)
        return {"available": False, "latest": None, "url": None}
