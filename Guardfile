guard 'phpunit', :cli => '--colors', :tests_path => 'tests', :keep_failed => true do
  watch(%r{^tests/.+Test\.php$})
  watch(%r{^lib/(.+)\.php$}) { |m| "tests/#{m[1]}Test.php" }
end
