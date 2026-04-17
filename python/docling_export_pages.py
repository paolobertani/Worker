#!/Applications/MacStack.app/Contents/Resources/usr/local/docling/bin/python

import argparse
import json
import logging
import sys
import time
from importlib.metadata import version
from pathlib import Path

from docling.datamodel.base_models import InputFormat
from docling.datamodel.pipeline_options import PdfPipelineOptions
from docling.document_converter import DocumentConverter, PdfFormatOption


# Configure logging to keep helper output machine-readable.

def configure_logging():
    logging.basicConfig( level = logging.ERROR )
    logging.getLogger( "docling" ).setLevel( logging.ERROR )
    logging.getLogger( "docling_core" ).setLevel( logging.ERROR )




# Build the Docling converter configured for Pinaxo PDFs with text in clear.

def build_document_converter():
    pipeline_options = PdfPipelineOptions()

    pipeline_options.do_ocr = False
    pipeline_options.do_table_structure = True
    pipeline_options.generate_page_images = False
    pipeline_options.generate_picture_images = False
    pipeline_options.do_picture_classification = False
    pipeline_options.do_picture_description = False
    pipeline_options.do_chart_extraction = False
    pipeline_options.do_code_enrichment = False
    pipeline_options.do_formula_enrichment = False
    pipeline_options.enable_remote_services = False
    pipeline_options.force_backend_text = False
    pipeline_options.document_timeout = 120

    return DocumentConverter(
        format_options = {
            InputFormat.PDF: PdfFormatOption( pipeline_options = pipeline_options ),
        }
    )




# Return the filename prefix used for emitted page markdown files.

def page_file_prefix_from_pdf_path( pdf_path ):
    return pdf_path.stem




# Write one text file atomically inside the pages cache.

def write_text_file_atomically( path, content ):
    temp_path = path.with_name( path.name + ".tmp" )
    temp_path.write_text( content, encoding = "utf-8" )
    temp_path.replace( path )




# Emit one JSON payload in a stable pretty-printed format.

def emit_json( payload ):
    print( json.dumps( payload, indent = 2, ensure_ascii = False ) )




# Build a success report for the batch execution.

def build_success_report( pdf_path, output_dir, page_start_zero, page_count, page_end_zero, pages_written, seconds_elapsed ):
    return {
        "status": "ok",
        "seconds": round( seconds_elapsed, 3 ),
        "docling_version": version( "docling" ),
        "pdf": str( pdf_path ),
        "output_dir": str( output_dir ),
        "page_file_prefix": page_file_prefix_from_pdf_path( pdf_path ),
        "page_start_zero": int( page_start_zero ),
        "page_count": int( page_count ),
        "page_end_zero": int( page_end_zero ),
        "pages_written": int( pages_written ),
    }




# Build an error report for the batch execution.

def build_error_report( pdf_path, output_dir, page_start_zero, page_count, seconds_elapsed, exc ):
    return {
        "status": "error",
        "seconds": round( seconds_elapsed, 3 ),
        "docling_version": version( "docling" ),
        "pdf": str( pdf_path ),
        "output_dir": str( output_dir ),
        "page_start_zero": int( page_start_zero ),
        "page_count": int( page_count ),
        "error_type": exc.__class__.__name__,
        "error": str( exc ),
    }




# Export one batch of PDF pages into per-page markdown files.

def export_markdown_pages( pdf_path, page_start_zero, page_count, output_dir ):
    output_dir.mkdir( parents = True, exist_ok = True )

    page_end_zero = page_start_zero + page_count - 1
    page_file_prefix = page_file_prefix_from_pdf_path( pdf_path )

    conversion_started_at = time.perf_counter()

    converter = build_document_converter()
    conversion_result = converter.convert(
        str( pdf_path ),
        page_range = ( page_start_zero + 1, page_end_zero + 1 ),
    )

    document = conversion_result.document
    pages_written = 0

    for page_zero in range( page_start_zero, page_end_zero + 1 ):
        page_markdown = document.export_to_markdown(
            page_no = page_zero + 1,
            page_break_placeholder = None,
            mark_meta = False,
            compact_tables = False,
            traverse_pictures = False,
            include_annotations = True,
            mark_annotations = False,
        )

        page_path = output_dir / f"{page_file_prefix}.{page_zero:04d}.page.md"
        write_text_file_atomically( page_path, page_markdown )
        pages_written += 1

    seconds_elapsed = time.perf_counter() - conversion_started_at

    return build_success_report(
        pdf_path = pdf_path,
        output_dir = output_dir,
        page_start_zero = page_start_zero,
        page_count = page_count,
        page_end_zero = page_end_zero,
        pages_written = pages_written,
        seconds_elapsed = seconds_elapsed,
    )




# Build the command line parser.

def build_argument_parser():
    parser = argparse.ArgumentParser()

    parser.add_argument( "--version", action = "store_true" )
    parser.add_argument( "--pdf" )
    parser.add_argument( "--page-start-zero", type = int )
    parser.add_argument( "--page-count", type = int )
    parser.add_argument( "--output-dir" )

    return parser




# Entrypoint.

def main():
    configure_logging()

    parser = build_argument_parser()
    args = parser.parse_args()

    if args.version:
        emit_json(
            {
                "status": "ok",
                "seconds": 0.0,
                "docling_version": version( "docling" ),
            }
        )
        return 0

    pdf_path = Path( args.pdf )
    output_dir = Path( args.output_dir )

    execution_started_at = time.perf_counter()

    try:
        payload = export_markdown_pages(
            pdf_path = pdf_path,
            page_start_zero = args.page_start_zero,
            page_count = args.page_count,
            output_dir = output_dir,
        )
    except Exception as exc:
        seconds_elapsed = time.perf_counter() - execution_started_at
        emit_json(
            build_error_report(
                pdf_path = pdf_path,
                output_dir = output_dir,
                page_start_zero = args.page_start_zero,
                page_count = args.page_count,
                seconds_elapsed = seconds_elapsed,
                exc = exc,
            )
        )
        return 1

    emit_json( payload )
    return 0




if __name__ == "__main__":
    sys.exit( main() )
