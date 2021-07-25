CREATE FUNCTION gettext(url TEXT) RETURNS TEXT
AS $$
import urllib2
try:
  f = urllib2.urlopen(url)
  return ''.join(f.readlines())
except Exception:
  return ""
$$ LANGUAGE plpythonu;
