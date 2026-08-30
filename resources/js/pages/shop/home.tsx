import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Headset, Quote, ShieldCheck, Sparkles, Star, Truck } from 'lucide-react';
import { ProductCard } from '@/components/shop/product-card';
import { ProductCarousel } from '@/components/shop/product-carousel';
import ShopLayout from '@/layouts/shop-layout';
import { useTranslation } from '@/lib/i18n';
import type { ProductCardData } from '@/lib/shop/catalog';
import type { SharedData } from '@/types';

/**
 * Background photo per category, keyed by slug. Files live in
 * public/images/categories/. A category with no entry falls back to a gradient.
 */
const CATEGORY_IMAGES: Record<string, string> = {
    gowns: '/images/categories/gowns.jpg',
    'modest-clothes': '/images/categories/modest-clothes.jpg',
    jewellery: '/images/categories/jewellery.jpg',
    bags: '/images/categories/bags.jpg',
    shoes: '/images/categories/shoes.jpg',
};

const BENEFITS = [
    { icon: Truck, title: 'Nationwide delivery', body: 'Complimentary over ৳15,000, everywhere in Bangladesh.' },
    { icon: Sparkles, title: 'Handcrafted', body: 'Made in small runs in our Dhaka atelier.' },
    { icon: ShieldCheck, title: 'Secure payment', body: 'bKash, Nagad or cash on delivery.' },
    { icon: Headset, title: 'Here to help', body: 'Styling and sizing support, seven days a week.' },
];

const TESTIMONIALS = [
    { quote: 'The gown fit like it was made for me — because it was. The teal is even richer in person.', name: 'Ayesha R.', city: 'Dhaka' },
    { quote: 'Ordered a clutch for my sister’s wedding. Beautiful finish and it arrived two days early.', name: 'Farhana K.', city: 'Chattogram' },
    { quote: 'Quietly luxurious. The details — the piping, the lining — are what you pay for, and they’re all there.', name: 'Nusrat J.', city: 'Sylhet' },
];

interface HomeProps {
    hero: ProductCardData | null;
    newArrivals: ProductCardData[];
    bestSelling: ProductCardData[];
    showcaseGowns: ProductCardData[];
    showcaseJewellery: ProductCardData[];
    showcaseBags: ProductCardData[];
}

/** A 4-piece category showcase: heading + a four-up grid, hidden when empty. */
function CategoryShowcase({ eyebrow, heading, href, products }: { eyebrow: string; heading: string; href: string; products: ProductCardData[] }) {
    if (products.length === 0) {
        return null;
    }

    return (
        <section className="mx-auto max-w-7xl px-5 py-16 lg:px-8">
            <SectionHead eyebrow={eyebrow} heading={heading} href={href} linkLabel="View all" />
            <div className="mt-10 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-4">
                {products.slice(0, 4).map((product) => (
                    <ProductCard key={product.slug} product={product} />
                ))}
            </div>
        </section>
    );
}

/** Small reusable section heading. */
function SectionHead({ eyebrow, heading, href, linkLabel }: { eyebrow: string; heading: string; href?: string; linkLabel?: string }) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p className="zt-eyebrow">{eyebrow}</p>
                <h2 className="font-display text-zt-ink mt-3 text-4xl sm:text-5xl">{heading}</h2>
            </div>
            {href && (
                <Link href={href} className="text-zt-teal hover:text-zt-teal-deep inline-flex items-center gap-2 text-[0.72rem] tracking-[0.2em] uppercase">
                    {linkLabel ?? 'View all'}
                    <ArrowRight className="size-4" />
                </Link>
            )}
        </div>
    );
}

export default function Home({ hero, newArrivals, bestSelling, showcaseGowns, showcaseJewellery, showcaseBags }: HomeProps) {
    const { shopCategories: categories = [] } = usePage<SharedData>().props;
    const { t, translations } = useTranslation();
    const categoryLabel = (slug: string, name: string) => translations[`category.${slug}`] ?? name;

    return (
        <ShopLayout
            title="Zaartaj Elegance — Gowns, Abaya & Occasion Wear"
            description="Gowns, abayas, bags, shoes and jewellery made in small runs in Dhaka. Complimentary delivery across Bangladesh over ৳15,000."
        >
            {/* ============================ Hero ============================ */}
            <section className="bg-zt-teal-deep relative flex min-h-[100svh] w-full items-center overflow-hidden">
                {/* Autoplay background video (muted + playsInline so browsers allow it);
                    the hero gown image is the poster shown until the video paints. */}
                <video
                    className="absolute inset-0 h-full w-full object-cover object-center"
                    src="/video/groom.mp4"
                    poster={hero?.image ?? undefined}
                    autoPlay
                    muted
                    loop
                    playsInline
                    preload="auto"
                    aria-hidden="true"
                />

                {/* Even wash keeps the centred text legible over the video, darker at the foot. */}
                <div className="bg-zt-teal-deep/45 absolute inset-0" />
                <div className="from-zt-teal-deep/70 absolute inset-0 bg-gradient-to-t via-transparent to-transparent" />
                <span className="pointer-events-none absolute inset-4 hidden ring-1 ring-zt-gold-light/25 sm:block lg:inset-6" />

                {/* Vertical "scroll to explore" cue on the left. */}
                <span className="absolute bottom-12 left-6 hidden rotate-180 text-[0.6rem] tracking-[0.35em] text-white/70 uppercase [writing-mode:vertical-rl] lg:block">
                    Scroll to explore
                </span>

                {/* Centred title only — no buttons, no paragraph. Nudged below centre
                    and coloured in the gold button tone. */}
                <div className="relative mx-auto mt-[18vh] flex w-full max-w-4xl flex-col items-center px-6 text-center">
                    <div className="flex items-center justify-center gap-4 text-white/90">
                        <span className="text-[0.6rem] font-medium tracking-[0.26em] uppercase sm:text-xs">Make your wedding dress</span>
                        <span className="bg-zt-gold hidden h-px w-14 sm:block" />
                        <span className="text-[0.6rem] font-medium tracking-[0.26em] uppercase sm:text-xs">As unique as your love story</span>
                    </div>
                    {/* White + gold mixture: white top line, gold bottom line. */}
                    <h1 className="font-display mt-8 text-5xl leading-[1.02] tracking-[0.06em] text-white uppercase drop-shadow-md sm:text-6xl lg:text-8xl">
                        Luxury Bridal
                        <span className="text-zt-gold block">Couture</span>
                    </h1>
                </div>
            </section>

            {/* ===================== Categories (slide) ===================== */}
            <section className="mx-auto max-w-7xl px-5 py-20 lg:px-8">
                <div className="text-center">
                    <p className="zt-eyebrow">{t('home.categories.eyebrow')}</p>
                    <h2 className="font-display text-zt-ink mt-3 text-4xl sm:text-5xl">{t('home.categories.heading')}</h2>
                    <div className="zt-rule mx-auto mt-6 max-w-xs" />
                </div>

                <div className="mt-12 flex snap-x snap-mandatory gap-5 overflow-x-auto pb-3 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    {categories.map((category) => {
                        // An admin-uploaded photo wins; otherwise fall back to the
                        // bundled house image for that slug, then the teal gradient.
                        const image = category.image ?? CATEGORY_IMAGES[category.slug];
                        return (
                            <Link
                                key={category.slug}
                                href={`/shop?category=${category.slug}`}
                                className="group relative flex aspect-[3/4] w-[62%] shrink-0 snap-start flex-col justify-end overflow-hidden p-6 sm:w-[40%] md:w-[30%] lg:w-[19%]"
                            >
                                {image ? (
                                    <img
                                        src={image}
                                        alt={categoryLabel(category.slug, category.name)}
                                        loading="lazy"
                                        className="absolute inset-0 h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-105"
                                    />
                                ) : (
                                    <span className="from-zt-teal-deep to-zt-teal absolute inset-0 bg-gradient-to-br" />
                                )}
                                <span className="from-zt-teal-deep/90 via-zt-teal-deep/20 absolute inset-0 bg-gradient-to-t to-transparent" />
                                <span className="font-display relative block text-2xl text-white drop-shadow-sm">{categoryLabel(category.slug, category.name)}</span>
                            </Link>
                        );
                    })}
                </div>
            </section>

            {/* ====================== New Arrivals ========================= */}
            <section className="bg-zt-sand/40 border-zt-sand border-y">
                <div className="mx-auto max-w-7xl px-5 py-20 lg:px-8">
                    <SectionHead eyebrow="Just in" heading="New Arrivals" href="/shop?sort=newest" linkLabel="View all" />
                    <div className="mt-12">
                        <ProductCarousel products={newArrivals} />
                    </div>
                </div>
            </section>

            {/* ======================= Best Selling ======================= */}
            <section className="mx-auto max-w-7xl px-5 py-20 lg:px-8">
                <SectionHead eyebrow="Most loved" heading="Best Selling" href="/shop" linkLabel="Shop all" />
                <div className="mt-12">
                    <ProductCarousel products={bestSelling} />
                </div>
            </section>

            {/* ==================== Editorial banner ====================== */}
            <section className="relative flex min-h-[70vh] items-end overflow-hidden lg:min-h-[88vh]">
                <img src="/images/zaartaj.png" alt="Zaartaj bridal gown" className="absolute inset-0 h-full w-full object-cover object-top" />
                {/* Foot-up wash so the caption reads while the name + gown stay bright above. */}
                <div className="from-zt-teal-deep/90 via-zt-teal-deep/20 absolute inset-0 bg-gradient-to-t to-transparent" />
                <div className="relative mx-auto w-full max-w-7xl px-6 pb-12 lg:px-10 lg:pb-16">
                    <div className="max-w-md">
                        <p className="text-zt-gold-light text-xs font-medium tracking-[0.28em] uppercase">The Bridal Edit</p>
                        <h2 className="font-display mt-5 text-4xl leading-tight text-white sm:text-5xl">
                            Made for the <span className="text-zt-gold-light italic">day itself</span>
                        </h2>
                        <p className="mt-5 max-w-sm text-base leading-relaxed text-white/80">
                            Hand-worked gowns and shararas, fitted to you at no extra cost. Begin three to four weeks before the date.
                        </p>
                        <Link
                            href="/shop?category=gowns"
                            className="bg-zt-gold hover:bg-zt-gold-light mt-8 inline-flex items-center gap-2 px-8 py-4 text-[0.72rem] font-medium tracking-[0.2em] text-white uppercase transition-colors"
                        >
                            Explore gowns
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </div>
            </section>

            {/* ============ Category showcases: 4 gowns / 4 jewellery / 4 bags ============ */}
            <CategoryShowcase eyebrow="The house" heading="Gowns" href="/shop?category=gowns" products={showcaseGowns} />
            <CategoryShowcase eyebrow="Finishing touches" heading="Jewellery" href="/shop?category=jewellery" products={showcaseJewellery} />
            <CategoryShowcase eyebrow="Carried in hand" heading="Bags" href="/shop?category=bags" products={showcaseBags} />

            {/* ====================== About Zaartaj ======================= */}
            <section className="bg-zt-sand/40 border-zt-sand border-y">
                <div className="mx-auto grid max-w-7xl items-center gap-10 px-5 py-20 lg:grid-cols-2 lg:gap-16 lg:px-8">
                    <div className="relative aspect-[4/5] overflow-hidden lg:aspect-[5/6]">
                        <img src="/images/modest.png" alt="Inside the Zaartaj boutique" className="h-full w-full object-cover" />
                        <span className="pointer-events-none absolute inset-4 ring-1 ring-zt-gold-light/40" />
                    </div>
                    <div>
                        <p className="zt-eyebrow">Our house</p>
                        <h2 className="font-display text-zt-ink mt-3 text-4xl leading-tight sm:text-5xl">About Zaartaj Elegance</h2>
                        <div className="zt-rule my-7 max-w-xs" />
                        <p className="text-zt-muted text-base leading-relaxed">
                            Zaartaj Elegance is a Dhaka atelier for occasion wear — gowns, abayas and the pieces that finish them. We work in small runs,
                            rarely more than twenty of a design, so the beading, piping and embroidery can be done by hand.
                        </p>
                        <p className="text-zt-muted mt-4 text-base leading-relaxed">
                            Our signature is emerald and gold: rich, quiet and made to be photographed. Every gown is cut to order and fitted to you.
                        </p>
                        <Link
                            href="/shop"
                            className="border-zt-teal text-zt-teal hover:bg-zt-teal mt-8 inline-flex items-center gap-2 border px-8 py-4 text-[0.72rem] font-medium tracking-[0.2em] uppercase transition-colors hover:text-white"
                        >
                            Discover the collection
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </div>
            </section>

            {/* ========================= Benefits ========================= */}
            <section className="mx-auto max-w-7xl px-5 py-16 lg:px-8">
                <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    {BENEFITS.map((benefit) => (
                        <div key={benefit.title} className="flex flex-col items-center px-2 text-center">
                            <span className="text-zt-teal ring-zt-teal/20 flex size-14 items-center justify-center rounded-full ring-1">
                                <benefit.icon className="size-6" />
                            </span>
                            <h3 className="font-display text-zt-ink mt-5 text-lg">{benefit.title}</h3>
                            <p className="text-zt-muted mt-1.5 text-sm leading-relaxed">{benefit.body}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* ====================== Offer banner ======================== */}
            <section className="relative flex min-h-[360px] items-center justify-center overflow-hidden text-center lg:min-h-[440px]">
                <img src="/images/categories/bags.jpg" alt="" className="absolute inset-0 h-full w-full object-cover object-center" />
                <div className="bg-zt-teal-deep/80 absolute inset-0" />
                <span className="pointer-events-none absolute inset-5 ring-1 ring-zt-gold-light/30 lg:inset-8" />
                <div className="relative mx-auto max-w-2xl px-6">
                    <p className="text-zt-gold-light text-xs font-medium tracking-[0.3em] uppercase">Offer</p>
                    <h2 className="font-display mt-5 text-4xl leading-tight text-white sm:text-5xl lg:text-6xl">
                        Free Delivery — <span className="text-zt-gold-light italic">on us</span>
                    </h2>
                    <p className="mx-auto mt-5 max-w-md text-base leading-relaxed text-white/80">
                        Complimentary nationwide delivery on every order over ৳15,000. No code needed.
                    </p>
                    <Link
                        href="/shop"
                        className="bg-zt-gold hover:bg-zt-gold-light mt-8 inline-flex items-center gap-2 px-9 py-4 text-[0.72rem] font-medium tracking-[0.2em] text-white uppercase transition-colors"
                    >
                        Shop now
                        <ArrowRight className="size-4" />
                    </Link>
                </div>
            </section>

            {/* ======================= Testimonials ======================= */}
            <section className="mx-auto max-w-7xl px-5 py-20 lg:px-8">
                <div className="text-center">
                    <p className="zt-eyebrow">Kind words</p>
                    <h2 className="font-display text-zt-ink mt-3 text-4xl sm:text-5xl">What our clients say</h2>
                    <div className="zt-rule mx-auto mt-6 max-w-xs" />
                </div>

                <div className="mt-14 grid gap-6 md:grid-cols-3">
                    {TESTIMONIALS.map((testimonial) => (
                        <figure key={testimonial.name} className="border-zt-sand bg-zt-cream relative flex flex-col border p-8">
                            <Quote className="text-zt-gold/40 size-8" />
                            <div className="mt-3 flex gap-0.5">
                                {Array.from({ length: 5 }).map((_, index) => (
                                    <Star key={index} className="fill-zt-gold text-zt-gold size-4" />
                                ))}
                            </div>
                            <blockquote className="text-zt-ink mt-4 flex-1 text-[0.95rem] leading-relaxed">{testimonial.quote}</blockquote>
                            <figcaption className="text-zt-muted mt-6 text-xs tracking-[0.16em] uppercase">
                                {testimonial.name} · {testimonial.city}
                            </figcaption>
                        </figure>
                    ))}
                </div>
            </section>
        </ShopLayout>
    );
}
