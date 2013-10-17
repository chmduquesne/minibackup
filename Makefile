all: build test zip

build:
	pandoc -c css/pandoc.css README.md -o README.html

test:
	sh test.sh

clean: zipclean
	rm -rf data/* README.html

zipclean:
	rm -f minibackup.zip

zip: zipclean
	zip -r minibackup.zip data/.htaccess css index.php lib Makefile README.html README.md test.sh samples

