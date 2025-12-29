Three-way benchmark comparison: uncommitted changes, current branch, and master.

## Approach: Detect State and Execute

1. **Detect state:**
   - `git status --porcelain` - check for uncommitted changes
   - `git branch --show-current` - get current branch name

2. **Execute benchmarks in sequence** (to minimize system load variance):

### If uncommitted changes exist:

```bash
mkdir --parent benchmarks/results
composer dump-autoload --optimize
php -d zend.assertions=-1 vendor/bin/phpbench run --progress=none --report=default > benchmarks/results/uncommitted.txt
git stash
```

### If not on master - benchmark current branch (committed code):

```bash
mkdir --parent benchmarks/results
composer dump-autoload --optimize
php -d zend.assertions=-1 vendor/bin/phpbench run --progress=none --report=default > benchmarks/results/<branch-name>.txt
```

### Benchmark master:

```bash
mkdir --parent benchmarks/results
git checkout master -- src/
composer dump-autoload --optimize
php -d zend.assertions=-1 vendor/bin/phpbench run --progress=none --report=default > benchmarks/results/master.txt
```

### Restore state:

```bash
git checkout <branch> -- src/
git stash pop # if stashed earlier
```

3. **Compare results:**
   - Read the benchmark output files
   - Display a comparison table showing performance differences
   - Highlight significant differences (>5% change)
   - Include memory comparison
   - Offer some interpretation of results based on the code differences

## Output Format

Present a Markdown table comparing all benchmarked versions:

| Benchmark | Master | Branch | Uncommitted | vs Master |
|-----------|--------|--------|-------------|-----------|
| ...       | ...    | ...    | ...         | +X% / -X% |

## Important Notes

- Always write the benchmark results to files to allow manual comparison
- Always run `composer dump-autoload --optimize` before each benchmark
- Use `--progress=none` to get clean table output
- Use `zend.assertions=-1` for production-like benchmarks
- Run benchmarks back-to-back to minimize system load variance
- Restore the working tree to its original state when done
