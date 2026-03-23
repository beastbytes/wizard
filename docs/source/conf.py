# Configuration file for the Sphinx documentation builder.
#
# For the full list of built-in configuration values, see the documentation:
# https://www.sphinx-doc.org/en/master/usage/configuration.html

# -- Project information -----------------------------------------------------
# https://www.sphinx-doc.org/en/master/usage/configuration.html#project-information

project = 'Wizard'
copyright = '2026, BeastBytes'
author = 'BeastBytes'

# -- General configuration ---------------------------------------------------
# https://www.sphinx-doc.org/en/master/usage/configuration.html#general-configuration

extensions = [
    'sphinxcontrib.mermaid',
    'sphinxcontrib.phpdomain'
]

from sphinx.highlighting import lexers
from pygments.lexers.web import PhpLexer

lexers['php'] = PhpLexer(startinline=True, linenos=1)

highlight_language = 'php'
templates_path = ['_templates']
exclude_patterns = []

# -- Options for HTML output -------------------------------------------------
# https://www.sphinx-doc.org/en/master/usage/configuration.html#options-for-html-output

html_favicon = '_static/favicon.png'
html_logo = '_static/logo.png'
html_theme = 'furo'
html_static_path = ['_static']
