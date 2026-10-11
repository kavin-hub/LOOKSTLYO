# Netlify Deployment Checklist

The site must deploy this repository root, where `index.html` and `netlify.toml` are located.

## Netlify settings

- Repository: the GitHub repository containing this project
- Branch: `main`
- Base directory: empty
- Build command: `echo Static site - no build required`
- Publish directory: `.`
- Functions directory: `netlify/functions`

After changing these settings, use **Clear cache and deploy site**.

## Verify the deploy

These URLs should work:

- `/`
- `/index.html`
- `/.netlify/functions/admin-login`

The admin function URL should return an HTTP method error for a browser GET, not a Netlify 404 page. That confirms the function is deployed.

## If the root still shows 404

The Netlify site is connected to the wrong GitHub repository or branch. Reconnect the site to the repository containing this root-level `index.html`, or create a new Netlify site by importing that repository.
